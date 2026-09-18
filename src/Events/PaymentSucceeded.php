<?php declare(strict_types=1); namespace Neok\Pay\Laravel\Events; use Neok\Pay\DTO\WebhookEvent; final readonly class PaymentSucceeded { public function __construct(public WebhookEvent $event) {} }
