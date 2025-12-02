<?php

#
# Parsedown
# https://parsedown.org
#
# (c) Emanuil Radoev
# http://erado.me
#
# For the full copyright and license information, please view the LICENSE
# file that was distributed with this source code.
#
class Parsedown
{
    # ~

    const version = '1.8.0';

    # ~

    function text($text)
    {
        $Elements = $this->textElements($text);

        # convert to markup
        $markup = $this->elements($Elements);

        # trim trailing whitespace
        $markup = rtrim($markup, "\n");

        return $markup;
    }

    function textElements($text)
    {
        # make sure that the text ends in a couple of newlines
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text, "\n");
        $text .= "\n\n";

        # standardize line breaks
        $text = str_replace("\n\n\n+", "\n\n", $text);

        $this->init();

        $this->lines = explode("\n", $text);

        return $this->document($this->lines);
    }

    #
    # Blocks
    #

    private $BlockTypes = [
        '#' => ['Header'],
        '*' => ['Rule', 'List'],
        '+' => ['List'],
        '-' => ['SetextHeader', 'Table', 'Rule', 'List'],
        '0' => ['List'],
        '1' => ['List'],
        '2' => ['List'],
        '3' => ['List'],
        '4' => ['List'],
        '5' => ['List'],
        '6' => ['List'],
        '7' => ['List'],
        '8' => ['List'],
        '9' => ['List'],
        '<' => ['Comment', 'Markup'],
        '=' => ['SetextHeader'],
        '>' => ['Quote'],
        '[' => ['Reference'],
        '_' => ['Rule'],
        '`' => ['FencedCode', 'IndentedCode'],
        '|' => ['Table'],
        '~' => ['FencedCode'],
    ];

    private $unmarkedBlockTypes = [
        'Code',
    ];

    private function document(array $lines)
    {
        $Elements = $this->parseBlocks($lines);

        return [
            'name' => 'div',
            'handler' => 'elements',
            'text' => $Elements,
        ];
    }

    private function parseBlocks(array $lines, $context = '')
    {
        $Elements = [];
        $Current = null;

        foreach ($lines as $line) {
            if (chop($line) === '') {
                if (isset($Current)) {
                    $Current['interrupted'] = true;
                }

                continue;
            }

            if (isset($Current)) {
                $indent = 0;
                while (isset($line[$indent]) and $line[$indent] === ' ') {
                    $indent++;
                }

                $text = $line;

                if ($Current['type'] === 'List' and $indent >= $Current['indent']) {
                    $text = str_repeat(' ', $Current['indent']) . $text;
                }

                $continue = $this->{'block' . $Current['type'] . 'Continue'}($Current, $text);

                if (isset($continue)) {
                    $Current = $continue;
                    continue;
                } else {
                    if ($this->is  ('Block', $Current['type'])) {
                        $this->{'block' . $Current['type'] . 'Complete'}($Current);
                    }

                    $Current = null;
                }
            }

            $marker = $line[0];

            # ~

            $block = null;

            if (isset($this->BlockTypes[$marker])) {
                foreach ($this->BlockTypes[$marker] as $blockType) {
                    $block = $this->{'block' . $blockType}($line, $Elements);

                    if (isset($block)) {
                        $block['type'] = $blockType;
                        break;
                    }
                }
            }

            if (!isset($block)) {
                foreach ($this->unmarkedBlockTypes as $blockType) {
                    $block = $this->{'block' . $blockType}($line, $Elements);

                    if (isset($block)) {
                        $block['type'] = $blockType;
                        break;
                    }
                }
            }

            if (isset($block)) {
                $Elements[] = $block;
                $Current = $block;

                continue;
            }

            array_pop($Elements);

            $block = $this->blockParagraph($line);
            $block['type'] = 'Paragraph';
            $Elements[] = $block;
            $Current = $block;
        }

        if (isset($Current) and $this->is('Block', $Current['type'])) {
            $this->{'block' . $Current['type'] . 'Complete'}($Current);
        }

        return $Elements;
    }

    private function blockHeader($line)
    {
        $level = 0;
        while (isset($line[$level]) and $line[$level] === '#') {
            $level++;
        }

        if ($level > 6) {
            return;
        }

        $text = trim($line, '# ');

        return [
            'name' => 'h' . $level,
            'handler' => 'line',
            'text' => $text,
        ];
    }

    private function blockRule($line)
    {
        $marker = $line[0];

        if (substr_count($line, $marker) < 3) {
            return;
        }

        if (trim($line, $marker) !== '') {
            return;
        }

        return [
            'name' => 'hr',
        ];
    }

    private function blockList($line)
    {
        $marker = $line[0];
        $indent = 0;

        if ($marker === '*' or $marker === '+' or $marker === '-') {
            $listType = 'ul';
        } else {
            $listType = 'ol';
        }

        while (isset($line[$indent]) and $line[$indent] === ' ') {
            $indent++;
        }

        $text = substr($line, $indent);

        if ($marker === '*' or $marker === '+' or $marker === '-') {
            if (!preg_match('/^[' . $marker . '][ ]/', $text)) {
                return;
            }
        } else {
            if (!preg_match('/^[0-9]+[\.][ ]/', $text)) {
                return;
            }
        }

        $text = preg_replace('/^[' . $marker . '][ ]/', '', $text);

        return [
            'name' => $listType,
            'handler' => 'elements',
            'text' => [
                [
                    'name' => 'li',
                    'handler' => 'line',
                    'text' => $text,
                ],
            ],
            'indent' => $indent,
        ];
    }

    private function blockListContinue(array $Block, $line)
    {
        $indent = 0;
        while (isset($line[$indent]) and $line[$indent] === ' ') {
            $indent++;
        }

        if ($indent < $Block['indent']) {
            return;
        }

        $text = substr($line, $indent);

        if ($Block['interrupted']) {
            if (trim($text) === '') {
                return $Block;
            }

            $Block['interrupted'] = false;
        }

        $Block['text'][0]['text'] .= "\n" . $text;

        return $Block;
    }

    private function blockListComplete(array $Block)
    {
        $Block['text'][0]['text'] = $this->text($Block['text'][0]['text']);

        return $Block;
    }

    private function blockSetextHeader($line, array $Elements)
    {
        if (!isset($Elements[count($Elements) - 1]) or $Elements[count($Elements) - 1]['type'] !== 'Paragraph') {
            return;
        }

        $level = $line[0] === '=' ? 1 : 2;

        $Elements[count($Elements) - 1] = [
            'name' => 'h' . $level,
            'handler' => 'line',
            'text' => $Elements[count($Elements) - 1]['text'],
        ];

        return $Elements[count($Elements) - 1];
    }

    private function blockComment($line)
    {
        if (strpos($line, '<!--') !== 0) {
            return;
        }

        return [
            'name' => 'comment',
            'text' => $line,
        ];
    }

    private function blockCommentContinue(array $Block, $line)
    {
        $Block['text'] .= "\n" . $line;

        if (strpos($line, '-->') !== false) {
            $Block['complete'] = true;
        }

        return $Block;
    }

    private function blockMarkup($line)
    {
        if (strpos($line, '<') !== 0) {
            return;
        }

        return [
            'name' => 'markup',
            'text' => $line,
        ];
    }

    private function blockMarkupContinue(array $Block, $line)
    {
        $Block['text'] .= "\n" . $line;

        if (strpos($line, '>') !== false) {
            $Block['complete'] = true;
        }

        return $Block;
    }

    private function blockQuote($line)
    {
        if (strpos($line, '>') !== 0) {
            return;
        }

        $text = trim($line, '> ');

        return [
            'name' => 'blockquote',
            'handler' => 'line',
            'text' => $text,
        ];
    }

    private function blockQuoteContinue(array $Block, $line)
    {
        if (strpos($line, '>') !== 0) {
            return;
        }

        $text = trim($line, '> ');

        $Block['text'] .= "\n" . $text;

        return $Block;
    }

    private function blockReference($line)
    {
        if (strpos($line, '[') !== 0) {
            return;
        }

        preg_match('/^\[(.+)\]:[ ]*(\S+)(?:[ ]+"(.+)")?/', $line, $matches);

        if (!isset($matches[1])) {
            return;
        }

        $id = strtolower($matches[1]);

        $this->DefinitionData['Reference'][$id] = [
            'url' => $matches[2],
            'title' => isset($matches[3]) ? $matches[3] : null,
        ];
    }

    private function blockFencedCode($line)
    {
        $marker = $line[0];
        $openerLength = strpos($line, $marker . $marker . $marker);

        if ($openerLength === false) {
            return;
        }

        $text = substr($line, $openerLength + 3);

        return [
            'name' => 'pre',
            'handler' => 'element',
            'text' => [
                'name' => 'code',
                'text' => $text,
            ],
            'marker' => $marker,
            'openerLength' => $openerLength,
        ];
    }

    private function blockFencedCodeContinue(array $Block, $line)
    {
        $marker = $Block['marker'];
        $openerLength = $Block['openerLength'];

        if (strpos($line, $marker . $marker . $marker) === $openerLength) {
            $Block['complete'] = true;
            return $Block;
        }

        $Block['text']['text'] .= "\n" . $line;

        return $Block;
    }

    private function blockIndentedCode($line)
    {
        if (strpos($line, '    ') !== 0) {
            return;
        }

        $text = substr($line, 4);

        return [
            'name' => 'pre',
            'handler' => 'element',
            'text' => [
                'name' => 'code',
                'text' => $text,
            ],
        ];
    }

    private function blockIndentedCodeContinue(array $Block, $line)
    {
        if (strpos($line, '    ') !== 0) {
            return;
        }

        $text = substr($line, 4);

        $Block['text']['text'] .= "\n" . $text;

        return $Block;
    }

    private function blockTable($line)
    {
        if (strpos($line, '|') === false) {
            return;
        }

        $elements = explode('|', $line);

        if (trim($elements[0]) === '') {
            array_shift($elements);
        }

        if (trim($elements[count($elements) - 1]) === '') {
            array_pop($elements);
        }

        foreach ($elements as &$element) {
            $element = trim($element);
        }

        return [
            'name' => 'table',
            'handler' => 'elements',
            'text' => [
                [
                    'name' => 'thead',
                    'handler' => 'elements',
                    'text' => [
                        [
                            'name' => 'tr',
                            'handler' => 'elements',
                            'text' => [
                                [
                                    'name' => 'th',
                                    'handler' => 'line',
                                    'text' => $elements,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function blockTableContinue(array $Block, $line)
    {
        if (strpos($line, '|') === false) {
            return;
        }

        $elements = explode('|', $line);

        if (trim($elements[0]) === '') {
            array_shift($elements);
        }

        if (trim($elements[count($elements) - 1]) === '') {
            array_pop($elements);
        }

        foreach ($elements as &$element) {
            $element = trim($element);
        }

        $Block['text'][0]['text'][0]['text'][] = [
            'name' => 'td',
            'handler' => 'line',
            'text' => $elements,
        ];

        return $Block;
    }

    private function blockParagraph($line)
    {
        return [
            'name' => 'p',
            'handler' => 'line',
            'text' => $line,
        ];
    }

    #
    # Spans
    #

    private $SpanTypes = [
        '`' => ['Code'],
        '!' => ['Image'],
        '[' => ['Link'],
        '_' => ['Emphasis'],
        '*' => ['Emphasis'],
        '~' => ['Strikethrough'],
        ':' => ['Url'],
    ];

    private $unmarkedSpanTypes = [
        'Email',
        'Url',
    ];

    private function line($text)
    {
        $Elements = $this->parseSpans($text);

        # convert to markup
        $markup = $this->elements($Elements);

        return $markup;
    }

    private function parseSpans($text)
    {
        $Elements = [];
        $Current = null;

        # ~

        $markers = [];

        foreach ($this->SpanTypes as $marker => $spanTypes) {
            $markers[$marker] = true;
        }

        foreach ($this->unmarkedSpanTypes as $spanType) {
            $markers[$spanType] = true;
        }

        # ~

        $position = 0;

        while (isset($text[$position])) {
            $marker = $text[$position];

            $span = null;

            if (isset($markers[$marker])) {
                foreach ($this->SpanTypes[$marker] as $spanType) {
                    $span = $this->{'inline' . $spanType}($text, $position);

                    if (isset($span)) {
                        $span['type'] = $spanType;
                        break;
                    }
                }
            }

            if (!isset($span)) {
                foreach ($this->unmarkedSpanTypes as $spanType) {
                    $span = $this->{'inline' . $spanType}($text, $position);

                    if (isset($span)) {
                        $span['type'] = $spanType;
                        break;
                    }
                }
            }

            if (isset($span)) {
                $Elements[] = $span;
                $position = $span['position'];

                continue;
            }

            $Elements[] = [
                'name' => 'text',
                'text' => $marker,
            ];

            $position++;
        }

        return $Elements;
    }

    private function inlineCode($text, $position)
    {
        if (strpos($text, '`') !== $position) {
            return;
        }

        $length = 1;
        while (isset($text[$position + $length]) and $text[$position + $length] === '`') {
            $length++;
        }

        $text = substr($text, $position + $length);

        $close = strpos($text, str_repeat('`', $length));

        if ($close === false) {
            return;
        }

        $text = substr($text, 0, $close);

        return [
            'name' => 'code',
            'text' => $text,
            'position' => $position + $length + $close + $length,
        ];
    }

    private function inlineImage($text, $position)
    {
        if (strpos($text, '![') !== $position) {
            return;
        }

        preg_match('/^!\[(.+)\]\((.+)\)/', substr($text, $position), $matches);

        if (!isset($matches[1])) {
            return;
        }

        return [
            'name' => 'img',
            'attributes' => [
                'alt' => $matches[1],
                'src' => $matches[2],
            ],
            'position' => $position + strlen($matches[0]),
        ];
    }

    private function inlineLink($text, $position)
    {
        if (strpos($text, '[') !== $position) {
            return;
        }

        preg_match('/^\[(.+)\]\((.+)\)/', substr($text, $position), $matches);

        if (!isset($matches[1])) {
            return;
        }

        return [
            'name' => 'a',
            'attributes' => [
                'href' => $matches[2],
            ],
            'handler' => 'line',
            'text' => $matches[1],
            'position' => $position + strlen($matches[0]),
        ];
    }

    private function inlineEmphasis($text, $position)
    {
        $marker = $text[$position];

        if (!isset($text[$position + 1]) or $text[$position + 1] !== $marker) {
            return;
        }

        $text = substr($text, $position + 2);

        $close = strpos($text, $marker . $marker);

        if ($close === false) {
            return;
        }

        $text = substr($text, 0, $close);

        return [
            'name' => 'strong',
            'handler' => 'line',
            'text' => $text,
            'position' => $position + 2 + $close + 2,
        ];
    }

    private function inlineStrikethrough($text, $position)
    {
        if (strpos($text, '~~') !== $position) {
            return;
        }

        $text = substr($text, $position + 2);

        $close = strpos($text, '~~');

        if ($close === false) {
            return;
        }

        $text = substr($text, 0, $close);

        return [
            'name' => 'del',
            'handler' => 'line',
            'text' => $text,
            'position' => $position + 2 + $close + 2,
        ];
    }

    private function inlineEmail($text, $position)
    {
        if (strpos($text, 'mailto:') !== $position) {
            return;
        }

        preg_match('/^mailto:(\S+)/', substr($text, $position), $matches);

        if (!isset($matches[1])) {
            return;
        }

        return [
            'name' => 'a',
            'attributes' => [
                'href' => $matches[0],
            ],
            'text' => $matches[1],
            'position' => $position + strlen($matches[0]),
        ];
    }

    private function inlineUrl($text, $position)
    {
        if (strpos($text, 'http') !== $position) {
            return;
        }

        preg_match('/^(https?:\/\/\S+)/', substr($text, $position), $matches);

        if (!isset($matches[1])) {
            return;
        }

        return [
            'name' => 'a',
            'attributes' => [
                'href' => $matches[0],
            ],
            'text' => $matches[0],
            'position' => $position + strlen($matches[0]),
        ];
    }

    #
    # Elements
    #

    private function elements(array $Elements)
    {
        $markup = '';

        foreach ($Elements as $Element) {
            if ($Element['name'] === 'text') {
                $markup .= $Element['text'];
                continue;
            }

            $markup .= $this->element($Element);
        }

        return $markup;
    }

    private function element(array $Element)
    {
        # https://html.spec.whatwg.org/multipage/syntax.html#void-elements
        $voidElements = [
            'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr',
        ];

        $markup = '<' . $Element['name'];

        if (isset($Element['attributes'])) {
            foreach ($Element['attributes'] as $name => $value) {
                $markup .= ' ' . $name . '="' . $value . '"';
            }
        }

        if (in_array($Element['name'], $voidElements)) {
            $markup .= '>';
        } else {
            $markup .= '>';

            if (isset($Element['handler'])) {
                $markup .= $this->{$Element['handler']}($Element['text']);
            } else {
                $markup .= $Element['text'];
            }

            $markup .= '</' . $Element['name'] . '>';
        }

        return $markup;
    }

    #
    # Fields
    #

    private $DefinitionData;

    #
    # Methods
    #

    private function init()
    {
        $this->DefinitionData = [];
    }

    private function is($type, $value)
    {
        return $type === $value;
    }
}
