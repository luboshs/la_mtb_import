<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    luboshs
 * @copyright since 2026 luboshs
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Translates product texts via the OpenAI Chat Completions API.
 */
class MtbOpenAiTranslator
{
    const API_URL = 'https://api.openai.com/v1/chat/completions';
    const TEMPERATURE = 0.2;
    const MAX_TOKENS = 1024;

    /**
     * Translate text to the target language using OpenAI.
     *
     * @param string $text Text to translate.
     * @param string $targetLang ISO 639-1 language code (e.g., 'en', 'de').
     * @param string $apiKey OpenAI API key.
     * @param string $model OpenAI model identifier.
     * @return string Translated text or original text on failure.
     */
    public function translate($text, $targetLang, $apiKey, $model)
    {
        if (empty(trim($text)) || empty($apiKey)) {
            return $text;
        }

        $safeTargetLang = preg_replace('/[^a-zA-Z\-]/', '', $targetLang);
        $payload = [
            'model' => $model,
            'temperature' => self::TEMPERATURE,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional translator specializing in railway model product descriptions. '
                        . 'Translate the following text to ' . $safeTargetLang . '. '
                        . 'Preserve technical terms, model numbers, and proper nouns. '
                        . 'Return only the translated text without any explanations.',
                ],
                [
                    'role' => 'user',
                    'content' => $text,
                ],
            ],
        ];

        $responseBody = $this->callApi($payload, $apiKey);

        if ($responseBody === null) {
            return $text;
        }

        $response = json_decode($responseBody, true);

        if (!is_array($response)
            || !isset($response['choices'][0]['message']['content'])
        ) {
            return $text;
        }

        return trim((string) $response['choices'][0]['message']['content']);
    }

    /**
     * Make an HTTP request to the OpenAI API.
     *
     * @param array $payload
     * @param string $apiKey
     * @return string|null Response body or null on failure.
     */
    protected function callApi(array $payload, $apiKey)
    {
        $ch = curl_init(self::API_URL);

        if ($ch === false) {
            return null;
        }

        $jsonPayload = json_encode($payload);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode !== 200) {
            return null;
        }

        return (string) $body;
    }
}
