@verbatim
/**
 * @method Activate activate()
 * @method Deactivate deactivate()
 */
enum Status: string implements StateMachineable
{
    use ManagesState;

    #[Transition(to: self::Inactive, using: Deactivate::class)]
    case Active = 'active';

    #[Transition(to: self::Active, using: Activate::class)]
    case Inactive = 'inactive';
}
@endverbatim
