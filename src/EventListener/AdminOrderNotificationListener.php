<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

#[AsEventListener(event: 'workflow.sylius_order_payment.completed.pay')]
final class AdminOrderNotificationListener
{
    public function __construct(
        private readonly SenderInterface $emailSender,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $payment = $event->getSubject();
        if (!$payment instanceof PaymentInterface) {
            $this->logger->warning('[AdminOrderNotification] Subject is not a PaymentInterface, got: ' . get_class($payment));
            return;
        }

        /** @var OrderInterface|null $order */
        $order = $payment->getOrder();
        if (null === $order) {
            $this->logger->warning('[AdminOrderNotification] Payment has no associated order.');
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
            $this->logger->info('[AdminOrderNotification] Email sent successfully.');
        } catch (\Throwable $e) {
            $this->logger->error('[AdminOrderNotification] Failed to send email: ' . $e->getMessage());
        }
    }
}
