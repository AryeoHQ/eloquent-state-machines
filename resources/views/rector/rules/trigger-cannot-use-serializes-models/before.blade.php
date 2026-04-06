@verbatim
use Illuminate\Queue\SerializesModels;
use Support\Actions\Concerns\AsAction;

abstract class Trigger implements Contracts\Trigger
{
    use AsAction;
    use SerializesModels;
}
@endverbatim
