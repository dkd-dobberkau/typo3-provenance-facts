<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend AJAX route behind the "Fact" button of the rich text editor: creates
 * a fact for the selected words of a content element. Goes through the
 * DataHandler, so the editor's permissions apply as in the regular form.
 */
final class CreateFactController
{
    private const TABLE = 'tx_provenancefacts_fact';
    private const CONFIDENCE = ['asserted', 'estimated', 'measured'];

    public function createAction(ServerRequestInterface $request): ResponseInterface
    {
        $input = $this->input($request);
        $errors = $this->validate($input);
        if ($errors !== []) {
            return new JsonResponse(['errors' => $errors], 400);
        }

        $content = BackendUtility::getRecord('tt_content', (int)$input['contentUid']);
        if ($content === null) {
            return new JsonResponse(['errors' => ['contentUid' => 'Content element not found. Save the element first.']], 404);
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            self::TABLE => [
                'NEWfact' => [
                    'pid' => (int)$content['pid'],
                    'content_uid' => (int)$content['uid'],
                    'fact_id' => $input['factId'],
                    'quote' => $input['quote'],
                    'subject' => $input['subject'],
                    'predicate' => $input['predicate'],
                    'object_literal' => $input['objectLiteral'],
                    'object_uri' => $input['objectUri'],
                    'source_url' => $input['sourceUrl'],
                    'confidence' => $input['confidence'],
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        $uid = (int)($dataHandler->substNEWwithIDs['NEWfact'] ?? 0);
        // The error log also carries notices (for example a fact id made unique),
        // so only a missing record counts as failure.
        if ($uid === 0) {
            return new JsonResponse(['errors' => ['general' => implode(' ', $dataHandler->errorLog) ?: 'The fact could not be saved.']], 403);
        }

        return new JsonResponse(['uid' => $uid, 'factId' => (string)BackendUtility::getRecord(self::TABLE, $uid)['fact_id']]);
    }

    /**
     * @return array<string, string>
     */
    private function input(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        if (!is_array($body) || $body === []) {
            $body = json_decode((string)$request->getBody(), true) ?: [];
        }
        $text = static fn(string $key): string => trim((string)($body[$key] ?? ''));

        return [
            'contentUid' => $text('contentUid'),
            'quote' => $text('quote'),
            'factId' => strtolower($text('factId')),
            'subject' => $text('subject'),
            'predicate' => $text('predicate'),
            'objectLiteral' => $text('objectLiteral'),
            'objectUri' => $text('objectUri'),
            'sourceUrl' => $text('sourceUrl'),
            'confidence' => $text('confidence') ?: 'asserted',
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string> messages by field
     */
    private function validate(array $input): array
    {
        $errors = [];
        if (!ctype_digit($input['contentUid']) || (int)$input['contentUid'] === 0) {
            $errors['contentUid'] = 'Save the content element first.';
        }
        foreach (['quote' => 'Select the words that state the fact.', 'subject' => 'Enter what the fact is about.', 'predicate' => 'Enter the property the fact states.'] as $field => $message) {
            if ($input[$field] === '') {
                $errors[$field] = $message;
            }
        }
        if (preg_match('/^[a-z0-9_-]{1,64}$/', $input['factId']) !== 1) {
            $errors['factId'] = 'Use lowercase letters, digits, "-" and "_".';
        }
        if ($input['objectLiteral'] === '' && $input['objectUri'] === '') {
            $errors['objectLiteral'] = 'Enter the value the fact states.';
        }
        if (!in_array($input['confidence'], self::CONFIDENCE, true)) {
            $errors['confidence'] = 'Unknown confidence.';
        }

        return $errors;
    }
}
