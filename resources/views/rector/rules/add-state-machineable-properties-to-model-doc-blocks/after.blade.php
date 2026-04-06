@verbatim
/**
 * @property (Status & \Support\Database\Eloquent\StateMachines\StateMachine) $status
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<Status> $status
 */
class User extends Model
{
    protected $casts = [
        'status' => Status::class,
    ];
}
@endverbatim
