@verbatim
class Activate extends Trigger
{
    #[Target]
    protected readonly User $user;

    public function allowed(): bool
    {
        return true;
    }
}
@endverbatim
