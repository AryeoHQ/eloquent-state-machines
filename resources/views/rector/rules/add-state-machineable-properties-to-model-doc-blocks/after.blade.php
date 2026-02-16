@verbatim
/**
 * @property Status $status
 */
class User extends Model
{
    protected $casts = [
        'status' => Status::class,
    ];
}
@endverbatim
