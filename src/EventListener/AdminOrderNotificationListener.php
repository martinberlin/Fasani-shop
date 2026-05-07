<?php

declare(strict_types=1);

namespace App\EventListener;

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
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            return;
        }

        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();
        $contactEmail = $channel->getContactEmail();
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

        $this->mailer->send($email);
    }
}
