<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\Event;

// sylius_payment_request: fires for Stripe and modern payment plugins
// sylius_order_payment.completed.pay: fallback for legacy/Payum-based gateways
#[AsEventListener(event: 'workflow.sylius_payment_request.completed.complete')]
#[AsEventListener(event: 'workflow.sylius_order_payment.completed.pay')]
final class AdminOrderNotificationListener
{
    /** Prevent duplicate emails if multiple events fire for the same order within the same request */
    private array $notifiedOrders = [];

    public function __construct(
        private readonly SenderInterface $emailSender,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Event $event): void
    {
        $this->logger->info('[AdminOrderNotification] Event fired: ' . get_class($event));

        $subject = $event->getSubject();
        $order = $this->resolveOrder($subject);

        if (null === $order) {
            $this->logger->warning('[AdminOrderNotification] Could not resolve Order from subject: ' . get_class($subject));
            return;
        }

        if (in_array($order->getNumber(), $this->notifiedOrders, true)) {
            $this->logger->info('[AdminOrderNotification] Already notified for order #' . $order->getNumber() . ', skipping.');
            return;
        }

        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();
        $contactEmail = $channel->getContactEmail();

        if (null === $contactEmail) {
            $this->logger->warning('[AdminOrderNotification] Channel has no contact email set, skipping.');
            return;
        }

        $this->logger->info('[AdminOrderNotification] Sending admin notification for order #' . $order->getNumber() . ' to ' . $contactEmail);

        try {
            $this->emailSender->send(
                'admin_order_notification',
                [$contactEmail],
                [
                    'order'      => $order,
                    'channel'    => $channel,
                    'localeCode' => $order->getLocaleCode(),
                ],
            );
            $this->notifiedOrders[] = $order->getNumber();
            $this->logger->info('[AdminOrderNotification] Email sent successfully.');
        } catch (\Throwable $e) {
            $this->logger->error('[AdminOrderNotification] Failed to send email: ' . $e->getMessage());
        }
    }

    private function resolveOrder(object $subject): ?OrderInterface
    {
        if ($subject instanceof PaymentInterface) {
            return $subject->getOrder();
        }

        if (method_exists($subject, 'getPayment')) {
            $payment = $subject->getPayment();
            if ($payment instanceof PaymentInterface) {
                return $payment->getOrder();
            }
        }

        return null;
    }
}
