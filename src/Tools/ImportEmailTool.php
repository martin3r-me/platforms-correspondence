<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Correspondence\Models\CorrespondenceItem;
use Platform\Correspondence\Services\ThreadResolver;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class ImportEmailTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCorrespondenceTeam;

    public function getName(): string
    {
        return 'correspondence.items.import_email.POST';
    }

    public function getDescription(): string
    {
        return 'POST /correspondence/items/import_email - Importiert eine Email aus MS365-Daten. Dedup via provider_id. Automatisches Thread-Resolving (ms365_conversation_id, In-Reply-To/References, Subject-Matching). Unterstützt to/cc/bcc als Array von {name, address}.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => 'Betreff der Email (erforderlich).',
                ],
                'sender_name' => [
                    'type' => 'string',
                    'description' => 'Name des Absenders.',
                ],
                'sender_email' => [
                    'type' => 'string',
                    'description' => 'Email des Absenders.',
                ],
                'recipient_name' => [
                    'type' => 'string',
                    'description' => 'Name des primären Empfängers (wird aus to[0] übernommen falls leer).',
                ],
                'recipient_email' => [
                    'type' => 'string',
                    'description' => 'Email des primären Empfängers (wird aus to[0] übernommen falls leer).',
                ],
                'to' => [
                    'type' => 'array',
                    'description' => 'TO-Empfänger als Array von {name, address}.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                        ],
                    ],
                ],
                'cc' => [
                    'type' => 'array',
                    'description' => 'CC-Empfänger als Array von {name, address}.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                        ],
                    ],
                ],
                'bcc' => [
                    'type' => 'array',
                    'description' => 'BCC-Empfänger als Array von {name, address}.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                        ],
                    ],
                ],
                'body_text' => [
                    'type' => 'string',
                    'description' => 'Plain-Text Body der Email.',
                ],
                'body_html' => [
                    'type' => 'string',
                    'description' => 'HTML Body der Email.',
                ],
                'direction' => [
                    'type' => 'string',
                    'enum' => ['inbound', 'outbound'],
                    'description' => 'Richtung der Email. Default: inbound.',
                ],
                'correspondence_date' => [
                    'type' => 'string',
                    'description' => 'Datum der Email (ISO 8601 oder YYYY-MM-DD).',
                ],
                'ms365_conversation_id' => [
                    'type' => 'string',
                    'description' => 'MS365 Conversation-ID für Thread-Gruppierung.',
                ],
                'ms365_message_id' => [
                    'type' => 'string',
                    'description' => 'MS365 Message-ID (wird als provider_id für Dedup verwendet).',
                ],
                'headers' => [
                    'type' => 'object',
                    'description' => 'Email-Headers: message_id, in_reply_to, references (Array), cc (Array), bcc (Array).',
                    'properties' => [
                        'message_id' => ['type' => 'string'],
                        'in_reply_to' => ['type' => 'string'],
                        'references' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'cc' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'bcc' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'required' => ['subject'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = $resolved['team_id'];
            $userId = $context->user->id;

            $subject = trim($arguments['subject'] ?? '');
            if ($subject === '') {
                return ToolResult::error('VALIDATION_ERROR', 'subject ist erforderlich.');
            }

            $providerId = $arguments['ms365_message_id'] ?? null;

            // Dedup check
            if ($providerId) {
                $existing = CorrespondenceItem::forTeam($teamId)
                    ->where('provider', 'ms365')
                    ->where('provider_id', $providerId)
                    ->first();

                if ($existing) {
                    return ToolResult::success([
                        'id' => $existing->id,
                        'uuid' => $existing->uuid,
                        'thread_id' => $existing->thread_id,
                        'duplicate' => true,
                        'message' => "Email existiert bereits (ID #{$existing->id}). Kein erneuter Import.",
                    ]);
                }
            }

            // Thread resolving
            $resolver = new ThreadResolver();
            $thread = $resolver->resolve($teamId, [
                'subject' => $subject,
                'ms365_conversation_id' => $arguments['ms365_conversation_id'] ?? null,
                'headers' => $arguments['headers'] ?? [],
            ], $userId);

            // Recipients
            $to = $arguments['to'] ?? [];
            $cc = $arguments['cc'] ?? [];
            $bcc = $arguments['bcc'] ?? [];

            $recipientName = $arguments['recipient_name'] ?? ($to[0]['name'] ?? null);
            $recipientEmail = $arguments['recipient_email'] ?? ($to[0]['address'] ?? null);

            // Build metadata
            $metadata = [
                'headers' => $arguments['headers'] ?? [],
                'to' => $to,
                'cc' => $cc,
                'bcc' => $bcc,
            ];

            // Create item
            $item = CorrespondenceItem::create([
                'team_id' => $teamId,
                'thread_id' => $thread->id,
                'type' => 'email',
                'status' => 'processed',
                'direction' => $arguments['direction'] ?? 'inbound',
                'sender_name' => $arguments['sender_name'] ?? null,
                'sender_email' => $arguments['sender_email'] ?? null,
                'recipient_name' => $recipientName,
                'recipient_email' => $recipientEmail,
                'body_text' => $arguments['body_text'] ?? null,
                'body_html' => $arguments['body_html'] ?? null,
                'metadata' => $metadata,
                'provider' => 'ms365',
                'provider_id' => $providerId,
                'correspondence_date' => $arguments['correspondence_date'] ?? now()->toDateString(),
                'is_read' => false,
                'created_by_user_id' => $userId,
            ]);

            return ToolResult::success([
                'id' => $item->id,
                'uuid' => $item->uuid,
                'thread_id' => $thread->id,
                'thread_uuid' => $thread->uuid,
                'thread_subject' => $thread->subject,
                'duplicate' => false,
                'message' => "Email importiert als Item #{$item->id} in Thread #{$thread->id}.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Email-Import: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['correspondence', 'email', 'import', 'ms365'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
