<?php

namespace App\Services\User\Features;

use App\Contracts\OpenAiServiceContract;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class UserAvatarGenerator
{
    private const STORAGE_DIRECTORY = 'avatars';

    private const AVATAR_SIZE = 100;

    private const REGENERATION_PROMPT = 'Regenerate. You mentioned the nickname/login/email or made a meta-comment. Do not discuss the input text. Describe only the fictional P2P character.';

    /**
     * @var array<int, string>
     */
    private const FORBIDDEN_META_PATTERNS = [
        '/\bник(?:нейм)?\b/ui',
        '/\bлогин\b/ui',
        '/\bemail\b/ui',
        '/\bпочта\b/ui',
        '/\bзвучит\b/ui',
        '/по имени/ui',
        '/как будто/ui',
        '/твой ник/ui',
        '/\bnickname\b/i',
        '/\blogin\b/i',
        '/\be-?mail\b/i',
        '/your nickname/i',
        '/your login/i',
        '/email instead of nickname/i',
        '/nickname sounds like/i',
        '/login sounds like/i',
    ];

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are generating a fictional avatar persona for a P2P payment platform user.

Input:
- login / nickname / email
- role

Important:
The login is hidden inspiration only. Never mention it directly. Never joke about the nickname, login, email, spelling, sound, or meaning. Do not say “your nickname”, “login sounds like”, “email instead of nickname”, “as if”, or similar meta-comments.

Never mention or comment on the nickname/login/email directly. Do not explain what the nickname sounds like, means, resembles, or suggests. Use it only as hidden inspiration for the character. The output must describe the fictional platform character, not the input text.

Write as if the user is already a chaotic character inside the P2P platform world.

Return three fields:

1. description:
Russian, 2–4 sentences. A funny, sharp, slightly rude portrait of the character in their platform role. Focus on behavior, habits, chaos, confidence, stress, disputes, money, traffic, liquidity, balances, clients, tickets, callbacks, or admin power. Make it feel like a roast of the person, not a comment about their name.

2. user_caption:
Russian, max 120 characters. One punchy joke about the character. No nickname/login/email/avatar mentions. No meta-comments. It should sound like a funny line under a profile icon.

3. image_prompt:
English. Concrete square avatar mascot prompt. Must include a specific character/object/animal, pose, facial expression, 2–4 visual details, flat vibrant illustration, centered subject, no text.

Role context:
- Trader: adrenaline, trust balance, requisites, SMS, disputes, stop_traffic, liquidity stress.
- Merchant: payouts, API, callbacks, merchant balance, clients, rates.
- Team Leader: herd of traders, reserve balance, commissions, insurance mode.
- Support: tickets, disputes, manual control, calm under fire.
- Super Admin: omniscient chaos manager, settings, bans, traffic pause.
- Analyst: charts, numbers, silent judgment.
- Provider Liquidity: deep pockets, provider balance.

Bad:
- “Почта вместо ника — чтобы всем сразу было ясно…”
- “Ник звучит так, будто…”
- “По логину видно, что…”

Good:
- “Трейдер с лицом человека, который закрыл спор ещё до того, как клиент понял, где кнопка.”
- “Он не нервничает — он просто так дышит, когда ликвидность тает.”
- “Две симки, три спора и уверенность размером с чужой резерв.”

Tone:
Cheeky, cocky, sarcastic, insider P2P humor. Funny first, stylish second. No HR tone. No generic compliments. No slurs, hate speech, explicit sexual content, or direct accusations.

Return ONLY valid JSON (no markdown fences):
{
  "description": "detailed Russian sarcastic text",
  "user_caption": "short Russian joke for the user",
  "image_prompt": "English prompt for image generation"
}
PROMPT;

    public function __construct(
        private readonly OpenAiServiceContract $openAi,
    ) {}

    /**
     * @return array{description: string, caption: string, avatar_url: string}
     */
    public function generate(User $user): array
    {
        $user->loadMissing('roles');

        $roleName = $user->roles->first()?->name ?? 'User';
        $login = $user->email;

        $parsed = $this->generatePersona(
            "Логин: {$login}\nРоль: {$roleName}",
        );

        $description = trim($parsed['description']);
        $caption = trim($parsed['user_caption']);
        $imagePrompt = trim($parsed['image_prompt']);

        if ($description === '' || $caption === '' || $imagePrompt === '') {
            throw new RuntimeException('OpenAI вернул пустое описание аватара.');
        }

        $imageContents = $this->openAi->generateImage(
            $this->buildImagePrompt($imagePrompt, $description),
        );

        $resizedPng = $this->resizeToPng($imageContents, self::AVATAR_SIZE);
        $storagePath = $this->storeAvatar($user, $resizedPng);

        $this->deletePreviousAvatar($user->avatar_path);

        $user->update([
            'avatar_path' => $storagePath,
            'avatar_description' => $description,
            'avatar_caption' => $caption,
            'avatar_generated_at' => now(),
            'avatar_generation_status' => 'completed',
            'avatar_generation_failed_at' => null,
            'avatar_generation_error' => null,
        ]);

        return [
            'description' => $description,
            'caption' => $caption,
            'avatar_url' => Storage::disk('public')->url($storagePath),
        ];
    }

    /**
     * @return array{description: string, user_caption: string, image_prompt: string}
     */
    private function generatePersona(string $prompt): array
    {
        $response = $this->openAi->prompt(
            prompt: $prompt,
            systemPrompt: self::SYSTEM_PROMPT,
        );

        $parsed = $this->parseJsonResponse($response);

        if (! $this->containsForbiddenMetaComment($parsed['description'], $parsed['user_caption'])) {
            return $parsed;
        }

        $retryResponse = $this->openAi->prompt(
            prompt: $prompt."\n\n".self::REGENERATION_PROMPT,
            systemPrompt: self::SYSTEM_PROMPT,
        );

        $retryParsed = $this->parseJsonResponse($retryResponse);

        if ($this->containsForbiddenMetaComment($retryParsed['description'], $retryParsed['user_caption'])) {
            throw new RuntimeException('OpenAI вернул мета-комментарий про логин вместо описания персонажа.');
        }

        return $retryParsed;
    }

    private function buildImagePrompt(string $imagePrompt, string $description): string
    {
        return 'Square avatar icon, 100x100 pixels style, flat vibrant illustration, '
            .'centered composition, no text, no letters, no watermark, clean background. '
            .$imagePrompt
            .' Character vibe (do not render as text): '.$description;
    }

    private function storeAvatar(User $user, string $pngContents): string
    {
        $directory = self::STORAGE_DIRECTORY.'/'.$user->id;
        $filename = Str::lower(Str::random(32)).'.png';
        $storagePath = $directory.'/'.$filename;

        Storage::disk('public')->makeDirectory($directory);

        $stored = Storage::disk('public')->put($storagePath, $pngContents);

        if ($stored === false) {
            throw new RuntimeException('Не удалось сохранить аватар.');
        }

        return $storagePath;
    }

    private function deletePreviousAvatar(?string $previousPath): void
    {
        if (! is_string($previousPath) || $previousPath === '') {
            return;
        }

        if (! str_starts_with($previousPath, self::STORAGE_DIRECTORY.'/')) {
            return;
        }

        if (Storage::disk('public')->exists($previousPath)) {
            Storage::disk('public')->delete($previousPath);
        }
    }

    private function resizeToPng(string $imageContents, int $size): string
    {
        $source = @imagecreatefromstring($imageContents);

        if ($source === false) {
            throw new RuntimeException('Не удалось обработать изображение от OpenAI.');
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $target = imagecreatetruecolor($size, $size);
        imagealphablending($target, false);
        imagesavealpha($target, true);

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $size,
            $size,
            $width,
            $height,
        );

        ob_start();
        imagepng($target);
        $png = ob_get_clean();

        if (! is_string($png) || $png === '') {
            throw new RuntimeException('Не удалось сжать аватар.');
        }

        return $png;
    }

    private function containsForbiddenMetaComment(string $description, string $caption): bool
    {
        $content = mb_strtolower($description."\n".$caption);

        foreach (self::FORBIDDEN_META_PATTERNS as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{description: string, user_caption: string, image_prompt: string}
     */
    private function parseJsonResponse(string $response): array
    {
        $trimmed = trim($response);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('OpenAI вернул некорректный JSON для описания аватара.');
        }

        $description = $decoded['description'] ?? null;
        $userCaption = $decoded['user_caption'] ?? null;
        $imagePrompt = $decoded['image_prompt'] ?? null;

        if (! is_string($description) || ! is_string($userCaption) || ! is_string($imagePrompt)) {
            throw new RuntimeException('OpenAI вернул неполный JSON для описания аватара.');
        }

        return [
            'description' => $description,
            'user_caption' => $userCaption,
            'image_prompt' => $imagePrompt,
        ];
    }
}
