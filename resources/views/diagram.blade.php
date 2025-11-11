@php
    use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
    use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;

    $cases = collect(class_exists($stateMachineable) ? $stateMachineable::cases() : [])->map(
        fn (StateMachineable $case) => (object) [
            'name' => $case->name,
            'transitions' => collect($case->transitions())->map(
                fn (Transition $transition) => (object) [
                    'to' => $transition->to->name,
                    'trigger' => str(class_basename($transition->using))->camel()->toString(),
                ]
            ),
        ]
    );
@endphp

<!-- diagram:{{ $stateMachineable }}:start -->
**`{{ $stateMachineable }}`**
```mermaid
stateDiagram-v2
@if($direction)
    direction {{ $direction }}
@endif
@if($cases->isNotEmpty())
    [*] --> {{ $cases->first()->name }}
@endif
@foreach($cases as $case)
@foreach($case->transitions as $transition)
    {{ $case->name }} --> {{ $transition->to }}: {{ $transition->trigger }}()
@endforeach
@endforeach
```
<!-- diagram:{{ $stateMachineable }}:end -->
