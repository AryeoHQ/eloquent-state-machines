<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use GuzzleHttp\ClientInterface as GuzzleClient;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\ObjectType;
use Psr\Http\Client\ClientInterface as PsrClient;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\WithoutTransaction;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<CallLike>
 */
#[NodeType(CallLike::class)]
final class TriggerCannotPerformIoInsideTransaction extends Rule
{
    /** @var array<int, class-string> */
    private const IO_CLASSES = [
        Http::class,
        Mail::class,
        Factory::class,
        PendingRequest::class,
        GuzzleClient::class,
        PsrClient::class,
        Mailer::class,
    ];

    /** @var Collection<int, non-falsy-string> */
    private Collection $ioNamespaces;

    /**
     * @param  array<array-key, string>  $ioNamespaces
     */
    public function __construct(array $ioNamespaces = [])
    {
        $this->ioNamespaces = collect($ioNamespaces)
            ->map(fn (string $namespace): string => trim($namespace, '\\').'\\')
            ->values();
    }

    /**
     * @param  CallLike  $node
     */
    public function shouldHandle(Node $node, Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null || $classReflection->isAbstract()) {
            return false;
        }

        if ($this->doesNotInherit($classReflection, Trigger::class)) {
            return false;
        }

        if ($this->isWithoutTransaction($classReflection)) {
            return false;
        }

        return $this->findIoClass($node, $scope) !== null;
    }

    /**
     * @param  CallLike  $node
     */
    public function handle(Node $node, Scope $scope): void
    {
        $this->error(
            sprintf(
                'A trigger cannot call `%s` inside its lifecycle transaction. Claim the row with a short trigger, make the call after the claim commits, and record the outcome with a second trigger. See README.md.',
                $this->findIoClass($node, $scope),
            ),
            $node->getStartLine(),
            'eloquentStateMachines.triggerCannotPerformIoInsideTransaction'
        );
    }

    private function isWithoutTransaction(ClassReflection $classReflection): bool
    {
        return collect([$classReflection, ...$classReflection->getParents()])->contains(
            fn (ClassReflection $class): bool => $class->getNativeReflection()->getAttributes(WithoutTransaction::class) !== []
        );
    }

    private function findIoClass(CallLike $node, Scope $scope): null|string
    {
        $classNames = match (true) {
            $node instanceof MethodCall => $scope->getType($node->var)->getObjectClassNames(),
            $node instanceof StaticCall && $node->class instanceof Node\Name => [$scope->resolveName($node->class)],
            $node instanceof StaticCall => $scope->getType($node->class)->getObjectClassNames(),
            default => [],
        };

        return collect($classNames)->first(fn (string $className): bool => $this->isIo($className));
    }

    private function isIo(string $className): bool
    {
        $type = new ObjectType($className);

        return collect(self::IO_CLASSES)->contains(fn (string $ioClass): bool => (new ObjectType($ioClass))->isSuperTypeOf($type)->yes())
            || $this->ioNamespaces->contains(fn (string $namespace): bool => str_starts_with(ltrim($className, '\\'), $namespace));
    }
}
