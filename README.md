# Eloquent State Machine
This package provides a State Pattern system for Eloquent.

## Installation
```bash
composer require aryeo/eloquent-state-machines
```

## Overview
State Machine is a simple implementation of the State Pattern comprised of three parts:
- States: Discrete modes a `Model` can be in.
- Transitions: Available movement between states.
- Triggers: Work performed to complete a transition.

## Usage
### Define States
States are defined and configured through a Backed Enum.

```php
namespace Users\Status;

use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;

enum Status: string implements StateMachineable
{
    use ManagesState;

    case Registered = 'registered';
    case Activated = 'activated';
    case Suspended = 'suspended';
}
```

### Associate Events

#### 1. Create Events
```php
namespace Users\Status\Events;

use Users\User;

class Activating
{
    public readonly User $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }
}
```

```php
namespace Users\Status\Events;

use Users\User;

class Activated
{
    public readonly User $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }
}
```

#### 2. Register events

> Note: Every case must have an `#[Events]` attribute. If one is missing, a `\Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions\NotDefined` exception will be thrown.

```php
namespace Users\Status;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;

enum Status: string implements StateMachineable
{
    use ManagesState;
    // ...

    #[Events(before: Activating::class, after: Activated::class)]
    case Activated = 'activated';

    // ..
}
```

> **Note:** The `after` event never runs inside the transaction — it fires only after the transition has committed. A throwing listener therefore cannot roll the transition back.

### Define Transitions
Transitions are represented by the target state and the trigger used to complete the operation.

#### 1. Create a Trigger
```php
namespace Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Users\User;

class Suspend extends Trigger
{
    #[Target]
    protected readonly User $user;

    public function allowed(): bool
    {
        return $this->user->is_not_trashed;
    }

    public function handle(): void
    {
        // business operations...
    }
}
```

> Note: The `#[Target]` attribute is required to specify which property contains the model being operated on.

A `Trigger` is a specialized [Action](https://github.com/AryeoHQ/actions) — it extends the `Action` contract and uses `AsAction` under the hood, so it can be run synchronously with `->now()` or dispatched to the queue with `->dispatch()`. On top of that, a `Trigger` adds state management, event dispatching, transition logic, and gate checks via `allowed()` and `blocked()`.

> **Note:** `SerializesModels` is left off the base `Trigger` so each trigger can opt in for itself. Without it (the default), a dispatched model is serialized as-is, so the worker sees the model exactly as it was at dispatch. Add `use \Illuminate\Queue\SerializesModels;` to a trigger when you want Laravel's default instead — storing only the model identifier and rehydrating fresh from the database when the job runs.

> **Note:** Even though a `Trigger` is an Action, it defines a `final prepare()` method to register lifecycle middleware (transaction boundaries, `before()`/`after()` hooks) that the state machine depends on. Use the `$middleware` property to add your own middleware, and the constructor or `handle()` method parameters for setup and dependency injection.

#### 2. Register Transition
```php
namespace Users\Status;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Users\Status\Triggers;

enum Status: string implements StateMachineable
{
    use ManagesState;

    // ...

    #[Events(before: Activating::class, after: Activated::class)]
    #[Transition(to: self::Suspended, using: Triggers\Suspend::class)]
    case Activated = 'activated';

    // ..
    case Suspended = 'suspended';
}
```

#### Stationary Transitions

A transition can target the current state. This is useful when a state represents a holding pattern — the trigger executes guarded logic without moving the model to a new state.

```php
#[Events(before: Locking::class, after: Locked::class)]
#[Transition(to: self::Locked, using: Triggers\Process::class)]
#[Transition(to: self::Succeeded, using: Triggers\Succeed::class)]
#[Transition(to: self::Failed, using: Triggers\Fail::class)]
case Locked = 'locked';
```

```mermaid
stateDiagram-v2
    direction LR
    [*] --> Pending
    Pending --> Locked: lock()
    Locked --> Locked: process()
    Locked --> Succeeded: succeed()
    Locked --> Failed: fail()
```

When a trigger targets the current state:
- `allowed()` is still evaluated
- `handle()` executes normally
- Before/after events are **not** dispatched
- The state column is **not** written

This lets you define named, guarded entry points for work within a state without introducing intermediate states into your graph.

#### Failures
When a particular `Trigger` fails the state machine will not be moved to the target state. However, you may want to alert your users or revert any actions that were partially completed by the trigger. To accomplish that, you may define a `failed` method on your `Trigger`. The `Throwable` instance that caused `handle()` to fail will be passed to `failed()`.

```php
namespace Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Throwable;
use Users\User;

class Upload extends Trigger
{
    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        // business operations that may throw an exception...
    }

    public function failed(Throwable $throwable): void
    {
        $this->user->status->suspend()->now();
    }
}
```

When the lifecycle transaction rolls back (a thrown exception, or a manual `fail()` outside the queue), the model is refreshed before `failed()` runs, so `failed()` sees the database's truth — not in-memory state left over from the rolled-back `handle()` work. On a queue worker a manual `fail()` commits the transaction rather than rolling it back, so no refresh occurs (see below).

#### Manually Failing a Trigger

Because a `Trigger` is an [Action](https://github.com/AryeoHQ/actions), it can mark itself as failed by calling `$this->fail()` — see [Manually Failing an Action](https://github.com/AryeoHQ/actions#manually-failing-an-action) for the contract (exceptions, per-path behavior, the fail-then-`return` convention).

What the state machine adds on top:

- The declared transition never lands and no after-event fires — the model ends wherever `failed()` routed it. The same applies to `$this->release()` on a queued run.
- **On a queue worker**, `failed()` routes the model *inside* the open lifecycle transaction — `handle()`'s work and the failure routing commit together, atomically.
- **Outside the queue** (`now()`, `dispatchSync()`), the lifecycle transaction rolls back (discarding `handle()`'s work) before `failed()` routes the model against fresh database state.

#### Opting Out of the Lifecycle Transaction

By default `before()` → `handle()` runs inside a `DB::transaction()`, so a thrown exception (or a `fail()` outside the queue) rolls back everything the trigger wrote — all-or-nothing state transitions are the core guarantee of the package.

Some triggers, though, do work the database can't take back: an HTTP call, publishing a message, sending an email. For those the transaction's guarantee doesn't fully apply — the side effect lands in the real world even when the surrounding writes roll back. Such a trigger may deliberately want its writes (audit rows, status stamps) to commit as they happen, so each run leaves a durable record alongside the side effect it performed.

Apply the `#[WithoutTransaction]` attribute to opt a trigger out of the transaction:

```php
namespace Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\WithoutTransaction;
use Users\User;

#[WithoutTransaction]
class Send extends Trigger
{
    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        // side effects a rollback can't reach...
    }
}
```

When present, `lifecycle()` runs `before()` → `handle()` without opening a transaction. Everything else is unchanged: phase transitions, events, and `failed()` semantics all behave as before.

Keep the following in mind when opting out:

- **Writes are not rolled back.** If `handle()` throws (or calls `fail()`), whatever it already wrote stays committed. Because there is nothing to roll back, the model is **not** refreshed before `failed()` runs — `failed()` sees the in-memory state `handle()` left behind.
- **A `Phase::Before` trigger stays transitioned on failure.** When the transition is written in `before()` (via `#[TransitionDuring(Phase::Before)]`), a later throw in `handle()` leaves the model in the new state, since there is no rollback. The default `Phase::After` transition never lands on a throw because `after()` is never reached.
- **The attribute is inheritable.** Resolution walks `class_parents`, mirroring `#[TransitionDuring(...)]`.
- **Nested transactions still apply.** A `#[WithoutTransaction]` trigger invoked inside another open transaction cannot escape it — its writes are only as durable as the outermost commit.

### Testing

A `Trigger` is an [Action](https://github.com/AryeoHQ/actions) — test it the same way. Focus on your business logic: `handle()`, `allowed()`, and `failed()`. The lifecycle plumbing (events, transitions, queue middleware) is handled by the package.

#### Testing handle()

Call `->now()` to execute the trigger synchronously and assert the side effects of your business logic.

```php
$user = User::factory()->registered()->create();

$user->status->activate()->now();

$this->assertNotNull($user->activated_at);
```

#### Testing allowed()

```php
$trigger = $user->status->suspend();

$this->assertTrue($trigger->allowed());
$this->assertFalse($trigger->blocked());
```

#### Testing failed()

If your trigger defines a `failed()` method, test it by triggering a failure through `->now()`:

```php
$user = User::factory()->registered()->create();

rescue(fn () => $user->status->upload()->now());

$this->assertEquals(Status::Suspended, $user->refresh()->status->enum);
```

#### Asserting a trigger was dispatched

When testing code that _dispatches_ a trigger (e.g., a controller or listener), use `::fake()` and `::assertFired()`:

```php
Activate::fake();

// ... code under test that dispatches Activate ...

Activate::assertFired();
```

> **Note:** `dispatch()` puts the trigger on the queue. You do not need to test `dispatch()` directly — the package tests the queue integration. Use `->now()` to test your trigger's behavior.

### Configure Model
```php
namespace Users;

use Users\Status\Status;

class User extends Model
{
    protected $attributes = [
        'status' => Status::Registered,
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }
}
```

### Usage
Once your model is configured with a state machine enum, you can access the state machine through the casted property (e.g., `$user->status`). This gives you access to trigger methods that correspond to your defined transitions.

### Examples
```php
namespace Users\Actions;

use Users\User;

class Suspend
{
    public function handle(User $user)
    {
        return $user->status->suspend()->now();
    }
}
```

```php
namespace Users\Policies;

use Illuminate\Auth\Authenticatable;
use Users\User;

class User
{
    // ...

    public function suspend(Authenticatable $authenticated, User $user)
    {
        if ($user->status->suspend()->blocked()) return false;

        // ...
    }
}
```

## Tooling
This package provides configuration for tooling that assist with development efforts and enforce the expectations / requirements when using this package.

## Generator
Scaffold a new state machine enum with:

```bash
php artisan make:state-machine Status --model=App\\Models\\User
```

This creates a backed enum implementing `StateMachineable` with the `ManagesState` trait, along with a co-located test file. If `--model` is omitted, you will be prompted to select one.

## Diagramming
To keep your documentation updated a command is included to create Markdown Diagrams of the available State Machines:

<!-- diagram:Tests\Fixtures\Support\Users\Status\Status:start -->
**`Tests\Fixtures\Support\Users\Status\Status`**
```mermaid
stateDiagram-v2
    direction LR
    [*] --> Registered
    note right of Registered: onboard()
    note right of Registered: ping()
    Registered --> Activated: activate()
    Registered --> Suspended: suspend()
    Activated --> Deactivated: deactivate()
```
<!-- diagram:Tests\Fixtures\Support\Users\Status\Status:end -->

### Usage
The command offers two primary outcomes.

1. Outputs the markdown for all of your `StateMachineables` to the terminal.
`php artisan state-machine:diagram`

    ```
    <!-- [starting-identifier] -->
    {{ MERMAID_MARKDOWN }}
    <!-- [ending-identifier]  -->
    ```


2. Automatically scans all of your project's markdown files for existing diagrams and updates them with the latest representation of a `StateMachineable` flow.
`php artisan state-machine:diagram --update`

    > ℹ️
    The comments in the example output above are required for the automatic scanning process to locate diagrams in project markdown files.
