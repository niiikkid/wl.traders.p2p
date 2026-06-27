<?php

namespace App\Support;

use Tiptap\Editor;
use Tiptap\Extensions\StarterKit;
use Tiptap\Extensions\TextAlign;
use Tiptap\Marks\Highlight;
use Tiptap\Marks\Underline;

class NewsTiptapEditor
{
    public static function make(): Editor
    {
        return new Editor([
            'extensions' => [
                new StarterKit,
                new Underline,
                new Highlight,
                new TextAlign([
                    'types' => ['paragraph', 'heading'],
                ]),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $contentJson
     */
    public static function jsonToHtml(array $contentJson): string
    {
        return self::make()->setContent($contentJson)->getHTML();
    }
}
