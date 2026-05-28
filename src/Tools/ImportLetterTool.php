<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Correspondence\Models\CorrespondenceItem;
use Platform\Correspondence\Services\ThreadResolver;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class ImportLetterTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCorrespondenceTeam;

    public function getName(): string
    {
        return 'correspondence.items.import_letter.POST';
    }

    public function getDescription(): string
    {
        return 'POST /correspondence/items/import_letter - Importiert einen Brief aus Claude-OCR-Extraktion. Betreff, Absender/Empfänger und Body als extrahierter Text.';
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
                    'description' => 'Betreff / Thema des Briefs (erforderlich).',
                ],
                'sender_name' => [
                    'type' => 'string',
                    'description' => 'Name des Absenders.',
                ],
                'sender_address' => [
                    'type' => 'string',
                    'description' => 'Adresse des Absenders.',
                ],
                'recipient_name' => [
                    'type' => 'string',
                    'description' => 'Name des Empfängers.',
                ],
                'recipient_address' => [
                    'type' => 'string',
                    'description' => 'Adresse des Empfängers.',
                ],
                'body_text' => [
                    'type' => 'string',
                    'description' => 'Extrahierter Text des Briefs.',
                ],
                'direction' => [
                    'type' => 'string',
                    'enum' => ['inbound', 'outbound'],
                    'description' => 'Richtung des Briefs. Default: inbound.',
                ],
                'correspondence_date' => [
                    'type' => 'string',
                    'description' => 'Datum des Briefs (YYYY-MM-DD).',
                ],
                'ocr_confidence' => [
                    'type' => 'number',
                    'description' => 'Optional: OCR-Konfidenz (0-1).',
                ],
                'thread_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Existierenden Thread verwenden statt neuen zu erstellen.',
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

            // Thread resolving
            if (!empty($arguments['thread_id'])) {
                $thread = \Platform\Correspondence\Models\CorrespondenceThread::forTeam($teamId)
                    ->find($arguments['thread_id']);
                if (!$thread) {
                    return ToolResult::error('NOT_FOUND', 'Thread nicht gefunden.');
                }
            } else {
                $resolver = new ThreadResolver();
                $normalized = $resolver->normalizeSubject($subject);

                // For letters, try subject match first, otherwise create new
                $thread = \Platform\Correspondence\Models\CorrespondenceThread::forTeam($teamId)
                    ->where('subject_normalized', $normalized)
                    ->orderByDesc('latest_item_at')
                    ->first();

                if (!$thread) {
                    $thread = \Platform\Correspondence\Models\CorrespondenceThread::create([
                        'team_id' => $teamId,
                        'subject' => $subject,
                        'subject_normalized' => $normalized,
                        'status' => 'inbox',
                        'created_by_user_id' => $userId,
                    ]);
                }
            }

            $metadata = [];
            if (!empty($arguments['ocr_confidence'])) {
                $metadata['ocr_confidence'] = (float) $arguments['ocr_confidence'];
            }
            if (!empty($arguments['sender_address'])) {
                $metadata['sender_address'] = $arguments['sender_address'];
            }
            if (!empty($arguments['recipient_address'])) {
                $metadata['recipient_address'] = $arguments['recipient_address'];
            }

            $item = CorrespondenceItem::create([
                'team_id' => $teamId,
                'thread_id' => $thread->id,
                'type' => 'letter',
                'status' => 'processed',
                'direction' => $arguments['direction'] ?? 'inbound',
                'sender_name' => $arguments['sender_name'] ?? null,
                'sender_email' => null,
                'recipient_name' => $arguments['recipient_name'] ?? null,
                'recipient_email' => null,
                'body_text' => $arguments['body_text'] ?? null,
                'body_html' => null,
                'metadata' => $metadata ?: null,
                'provider' => 'scan',
                'provider_id' => null,
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
                'message' => "Brief importiert als Item #{$item->id} in Thread #{$thread->id}.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Brief-Import: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['correspondence', 'letter', 'import', 'scan', 'ocr'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
