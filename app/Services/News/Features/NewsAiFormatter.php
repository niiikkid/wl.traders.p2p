<?php

namespace App\Services\News\Features;

use App\Support\NewsTiptapEditor;
use JsonException;
use RuntimeException;

class NewsAiFormatter
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a senior editor for an internal news feed of a P2P payment platform. Audience: traders, support, team leaders.

Task: turn a rough draft into a clear, scannable news post in Russian. Do not invent facts, numbers, dates, or promises.

Return ONLY valid JSON (no markdown fences):
{
  "title": "string, concise headline up to 120 characters",
  "content_json": { "type": "doc", "content": [ ... ] }
}

Editorial structure (use what fits the draft):
1. Opening paragraph — 1–2 sentences: what happened and who is affected.
2. Section headings (heading level 2) for logical blocks: «Что изменилось», «Что нужно сделать», «Сроки», etc.
3. bulletList — for 2+ parallel items, features, or rules.
4. orderedList — for numbered steps or a sequence.
5. blockquote — for warnings, deadlines, or one critical takeaway (start with ⚠️ or 💡 when appropriate).
6. horizontalRule — between major sections if the post is long.
7. Short paragraphs (1–3 sentences). Avoid walls of text.

Emphasis rules (TipTap marks):
- bold — key terms, roles, statuses, actions.
- highlight — the single most important phrase per block (deadline, amount, date, «обязательно», «срочно»). Do not overuse.
- italic — clarifications or secondary emphasis.
- underline — sparingly, only for named entities if needed.

Allowed nodes: doc, paragraph, heading (level 2 or 3 only), bulletList, orderedList, listItem, blockquote, horizontalRule.
Allowed marks: bold, italic, underline, strike, highlight, code.
paragraph and heading may have attrs: {"textAlign":"left"|"center"|"right"} — center only for short announcements.
listItem must wrap paragraph(s). heading must contain text nodes only.

Emoji: use sparingly at section starts or in blockquotes (🔥 ✅ 📢 ⚠️ 💡).

Example fragment:
{
  "type": "doc",
  "content": [
    {"type":"paragraph","content":[{"type":"text","text":"Кратко: с завтрашнего дня меняются лимиты на выплаты для трейдеров."}]},
    {"type":"heading","attrs":{"level":2},"content":[{"type":"text","text":"Что изменилось"}]},
    {"type":"bulletList","content":[
      {"type":"listItem","content":[{"type":"paragraph","content":[
        {"type":"text","text":"Лимит "},
        {"type":"text","marks":[{"type":"bold"}],"text":"на одну выплату"},
        {"type":"text","text":" увеличен"}
      ]}]}
    ]},
    {"type":"blockquote","content":[{"type":"paragraph","content":[
      {"type":"text","marks":[{"type":"bold"}],"text":"⚠️ Важно: "},
      {"type":"text","marks":[{"type":"highlight"}],"text":"до 15 июля"},
      {"type":"text","text":" нужно обновить реквизиты."}
    ]}]}
  ]
}
PROMPT;

    /**
     * @return array{title: ?string, content_json: array<string, mixed>, content_html: string}
     */
    public function format(string $rawText, ?string $title = null): array
    {
        $text = trim($rawText);

        if ($text === '') {
            throw new RuntimeException('Добавьте текст для оформления.');
        }

        $userPrompt = $text;

        if (is_string($title) && trim($title) !== '') {
            $userPrompt = 'Черновик заголовка: '.trim($title)."\n\nТекст:\n".$text;
        }

        $response = services()->openAi()->prompt(
            prompt: $userPrompt,
            systemPrompt: self::SYSTEM_PROMPT,
        );

        $parsed = $this->parseJsonResponse($response);

        /** @var array<string, mixed> $contentJson */
        $contentJson = $this->normalizeContentJson($parsed['content_json']);

        $formattedTitle = $parsed['title'] ?? null;

        return [
            'title' => is_string($formattedTitle) && trim($formattedTitle) !== ''
                ? trim($formattedTitle)
                : null,
            'content_json' => $contentJson,
            'content_html' => NewsTiptapEditor::jsonToHtml($contentJson),
        ];
    }

    /**
     * @return array{title?: mixed, content_json: array<string, mixed>}
     */
    private function parseJsonResponse(string $response): array
    {
        $trimmed = trim($response);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Не удалось разобрать ответ AI. Попробуйте ещё раз.');
        }

        if (! is_array($decoded) || ! isset($decoded['content_json']) || ! is_array($decoded['content_json'])) {
            throw new RuntimeException('AI вернул некорректный формат.');
        }

        if (($decoded['content_json']['type'] ?? null) !== 'doc') {
            throw new RuntimeException('AI вернул некорректную структуру текста.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $contentJson
     * @return array<string, mixed>
     */
    private function normalizeContentJson(array $contentJson): array
    {
        if (! isset($contentJson['content']) || ! is_array($contentJson['content'])) {
            $contentJson['content'] = [];
        }

        $contentJson['content'] = array_values(array_filter(
            array_map(fn (mixed $node): ?array => $this->normalizeNode(is_array($node) ? $node : null), $contentJson['content']),
            fn (?array $node): bool => $node !== null,
        ));

        return $contentJson;
    }

    /**
     * @param  array<string, mixed>|null  $node
     * @return array<string, mixed>|null
     */
    private function normalizeNode(?array $node): ?array
    {
        if ($node === null || ! isset($node['type'])) {
            return null;
        }

        $allowedBlocks = [
            'paragraph', 'heading', 'bulletList', 'orderedList',
            'listItem', 'blockquote', 'horizontalRule',
        ];
        $allowedMarks = ['bold', 'italic', 'underline', 'strike', 'highlight', 'code'];

        if ($node['type'] === 'heading') {
            $level = (int) ($node['attrs']['level'] ?? 2);
            $node['attrs']['level'] = match (true) {
                $level <= 2 => 2,
                default => 3,
            };
        }

        if (in_array($node['type'], ['bulletList', 'orderedList', 'blockquote'], true) && isset($node['content'])) {
            $node['content'] = array_values(array_filter(
                array_map(fn (mixed $child): ?array => $this->normalizeNode(is_array($child) ? $child : null), $node['content']),
                fn (?array $child): bool => $child !== null,
            ));

            return $node['content'] === [] ? null : $node;
        }

        if ($node['type'] === 'listItem' && isset($node['content'])) {
            $node['content'] = array_values(array_filter(
                array_map(fn (mixed $child): ?array => $this->normalizeNode(is_array($child) ? $child : null), $node['content']),
                fn (?array $child): bool => $child !== null,
            ));

            return $node['content'] === [] ? null : $node;
        }

        if ($node['type'] === 'paragraph' || $node['type'] === 'heading') {
            if (! isset($node['content']) || ! is_array($node['content'])) {
                return null;
            }

            $node['content'] = array_values(array_filter(
                array_map(function (mixed $child) use ($allowedMarks): ?array {
                    if (! is_array($child) || ($child['type'] ?? '') !== 'text') {
                        return null;
                    }

                    if (! is_string($child['text'] ?? null) || $child['text'] === '') {
                        return null;
                    }

                    if (isset($child['marks']) && is_array($child['marks'])) {
                        $child['marks'] = array_values(array_filter(
                            $child['marks'],
                            fn (mixed $mark): bool => is_array($mark)
                                && in_array($mark['type'] ?? '', $allowedMarks, true),
                        ));

                        if ($child['marks'] === []) {
                            unset($child['marks']);
                        }
                    }

                    return $child;
                }, $node['content']),
                fn (?array $child): bool => $child !== null,
            ));

            if ($node['content'] === []) {
                return null;
            }

            if (isset($node['attrs']['textAlign']) && ! in_array($node['attrs']['textAlign'], ['left', 'center', 'right'], true)) {
                unset($node['attrs']['textAlign']);
            }

            return $node;
        }

        if ($node['type'] === 'horizontalRule') {
            return $node;
        }

        if (! in_array($node['type'], $allowedBlocks, true)) {
            return null;
        }

        return $node;
    }
}
