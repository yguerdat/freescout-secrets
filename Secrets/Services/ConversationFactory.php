<?php

namespace Modules\Secrets\Services;

use App\Conversation;
use App\Customer;
use App\Mailbox;
use App\Thread;

/**
 * Creates the FreeScout conversation that backs an inbound (customer -> agent)
 * secret. The thread body contains only a notice — never the secret itself.
 * The encrypted payload is revealed on demand from the conversation view.
 */
class ConversationFactory
{
    public function createForInbound(string $email, string $subject, string $note = ''): Conversation
    {
        $mailbox = $this->targetMailbox();
        if (!$mailbox) {
            throw new \RuntimeException('Secrets: no mailbox available for inbound secrets.');
        }

        $customer = Customer::create($email, []);
        if (!$customer) {
            throw new \RuntimeException('Secrets: invalid customer email.');
        }

        $lines = ['<p>' . e(__('This customer submitted secure information through the encrypted intake form.')) . '</p>'];
        if ($note !== '') {
            $lines[] = '<p><strong>' . e(__('Customer note:')) . '</strong><br>' . nl2br(e($note)) . '</p>';
        }
        $lines[] = '<p><em>' . e(__('The secret is end-to-end encrypted. Use the "Reveal secret" panel above to display it; it is destroyed when its retention period ends.')) . '</em></p>';

        $threads = [[
            'type'        => Thread::TYPE_CUSTOMER,
            'body'        => implode('', $lines),
            'source_via'  => Thread::PERSON_CUSTOMER,
            'source_type' => Thread::SOURCE_TYPE_WEB,
            'customer_id' => $customer->id,
        ]];

        $data = [
            'type'        => Conversation::TYPE_EMAIL,
            'subject'     => $subject !== '' ? $subject : __('Secure information'),
            'mailbox_id'  => $mailbox->id,
            'source_type' => Conversation::SOURCE_TYPE_WEB,
            'source_via'  => Conversation::PERSON_CUSTOMER,
            'state'       => Conversation::STATE_PUBLISHED,
            'status'      => Conversation::STATUS_ACTIVE,
        ];

        $result = Conversation::create($data, $threads, $customer);

        // Conversation::create() returns ['conversation' => ..., 'thread' => ...]
        // or false when no thread could be created.
        if (empty($result['conversation'])) {
            throw new \RuntimeException('Secrets: conversation could not be created.');
        }

        return $result['conversation'];
    }

    private function targetMailbox(): ?Mailbox
    {
        $id = (int) (\Option::get('secrets.inbound_mailbox_id') ?: 0);
        if ($id) {
            $mailbox = Mailbox::find($id);
            if ($mailbox) {
                return $mailbox;
            }
        }
        return Mailbox::first();
    }
}
