@verbatim
class MyTrigger extends Trigger
{
    #[Target]
    protected readonly User $user;

    public function allowed(): bool
    {
        return true;
    }
}
@endverbatim
