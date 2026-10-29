@php
    use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
    use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;

    $cases = collect(class_exists($stateMachineable) ? $stateMachineable::cases() : [])->map(
        fn (StateMachineable $case) => (object) [
            'name' => $case->name,
            'transitions' => collect($case->transitions())->map(
                fn (Transition $transition) => (object) [
                    'to' => $transition->to->name,
                    'label' => str(class_basename($transition->using))->camel()->append('()')->when(
                        $transition->events,
                        fn ($label) => $label->append(' / ', class_basename($transition->events->before), ', ', class_basename($transition->events->after))
                    )->toString(),
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
@if($case->name === $transition->to)
    note right of {{ $case->name }}: {{ $transition->label }}
@else
    {{ $case->name }} --> {{ $transition->to }}: {{ $transition->label }}
@endif
@endforeach
@endforeach
```
<!-- diagram:{{ $stateMachineable }}:end -->
