<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use GuzzleHttp\ClientInterface as GuzzleClient;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Psr\Http\Client\ClientInterface as PsrClient;
use Psr\Http\Message\RequestInterface;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

class TriggerPerformsIo extends Trigger
{
    #[Target]
    public readonly User $user;

    public function handle(
        Factory $factory,
        PendingRequest $request,
        GuzzleClient $guzzle,
        PsrClient $psr,
        RequestInterface $psrRequest,
        Mailer $mailer,
        null|PsrClient $optionalPsr,
    ): void {
        Http::post('https://example.com/users', ['id' => $this->user->id]);
        Mail::raw('Activated', fn () => null);
        $factory->post('https://example.com/users');
        $request->post('https://example.com/users');
        $guzzle->request('POST', 'https://example.com/users');
        $psr->sendRequest($psrRequest);
        $mailer->raw('Activated', fn () => null);
        $optionalPsr?->sendRequest($psrRequest);
    }
}
