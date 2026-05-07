<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Workflow\Event\CompletedEvent;

#[AsEventListener(event: 'workflow.sylius_order_checkout.completed.complete')]
final class AdminOrderNotificationListener
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $this->logger->info('[AdminOrderNotification] Event fired: workflow.sylius_order_checkout.completed.complete');

        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            $this->logger->warning('[AdminOrderNotification] Subject is not an OrderInterface, got: ' . get_class($order));
            return;
        }

        $this->logger->info('[AdminOrderNotification] Order detected: #' . $order->getNumber());

        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();
        $contactEmail = $channel->getContactEmail();

        $this->logger->info('[AdminOrderNotification] Channel contact email: ' . ($contactEmail ?? 'NULL - aborting'));

        if (null === $contactEmail) {
            return;
        }

        $customer = $order->getCustomer();
        $total = number_format($order->getTotal() / 100, 2);
        $currency = $order->getCurrencyCode();

        $email = (new Email())
            ->to(new Address($contactEmail))
            ->subject(sprintf('[Fasani Shop] New order #%s — %s %s', $order->getNumber(), $total, $currency))
            ->html(sprintf(
                '<h2>New order received</h2>
                <p><strong>Order:</strong> #%s</p>
                <p><strong>Customer:</strong> %s (%s)</p>
                <p><strong>Total:</strong> %s %s</p><br><b>Ship to:</b><br>%s<br>%s (%s)<br>%s',
                $order->getNumber(),
                $customer?->getFullName(),
                $customer?->getEmail(),
                $total,
                $currency,
                $order->getShippingAddress()->getStreet(),
                $order->getShippingAddress()->getCity(),
                $order->getShippingAddress()->getPostcode(),
                $order->getShippingAddress()->getCountryCode()
            ));

        try {
            $this->mailer->send($email);
            $this->logger->info('[AdminOrderNotification] Email sent successfully to: ' . $contactEmail);
        } catch (\Throwable $e) {
            $this->logger->error('[AdminOrderNotification] Failed to send email: ' . $e->getMessage());
        }
    }
}
