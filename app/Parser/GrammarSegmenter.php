<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class GrammarSegmenter
{
    /** @return list<string> */
    public function split(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];

        // First split hard separators while respecting quotes and parentheses.
        $parts = $this->splitAware($text, false);
        $result = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;

            // Ampersand separates offers only outside quoted names/parentheses.
            foreach ($this->splitAware($part, true) as $piece) {
                $piece = trim($piece);
                if ($piece === '') continue;

                $counted = preg_split(
                    '/\s+(?=\d+\s+[A-Za-z][A-Za-z\'’ -]{2,}\b)/u',
                    $piece
                ) ?: [$piece];

                foreach ($counted as $entry) {
                    $entry = trim($entry, " \t\n\r\0\x0B|;,");
                    if ($entry !== '') $result[] = $entry;
                }
            }
        }
        return array_values($result);
    }

    /** @return list<string> */
    private function splitAware(string $text, bool $ampersandOnly): array
    {
        $out=[]; $buf=''; $quote=null; $depth=0; $len=strlen($text);
        for($i=0;$i<$len;$i++) {
            $ch=$text[$i];
            if (($ch==='"' || $ch==="'") && ($i===0 || $text[$i-1] !== '\\')) {
                $quote = $quote === null ? $ch : ($quote === $ch ? null : $quote);
                $buf.=$ch; continue;
            }
            if ($quote===null) {
                if ($ch==='(' || $ch==='[') $depth++;
                elseif (($ch===')' || $ch===']') && $depth>0) $depth--;
            }
            if ($quote===null && $depth===0) {
                if ($ampersandOnly) {
                    if ($ch==='&' && ($i===0 || ctype_space($text[$i-1])) && ($i+1===$len || ctype_space($text[$i+1]))) {
                        $left = mb_strtolower(trim($buf));
                        $right = mb_strtolower(trim(substr($text, $i + 1)));
                        // "weapons & shields" is one generic category phrase, not two offers.
                        if (preg_match('/\bweapons?$/u', $left) && preg_match('/^shields?\b/u', $right)) {
                            $buf.=$ch; continue;
                        }
                        if (trim($buf)!=='') $out[]=trim($buf); $buf=''; continue;
                    }
                } else {
                    $two = $i+1<$len ? $ch.$text[$i+1] : '';
                    if ($ch==='|' || $ch===';' || $ch==='~' || $ch==='^' || $two==='//') {
                        if ($ch==='^' && $i>0 && $i+1<$len && ctype_digit($text[$i-1]) && ctype_digit($text[$i+1])) { $buf.=$ch; continue; }
                        if (trim($buf)!=='') $out[]=trim($buf); $buf='';
                        if ($two==='//') $i++;
                        continue;
                    }
                    if ($ch==='-' && $i>0 && $i+1<$len && ctype_space($text[$i-1]) && ctype_space($text[$i+1])) {
                        if (trim($buf)!=='') $out[]=trim($buf); $buf=''; continue;
                    }
                }
            }
            $buf.=$ch;
        }
        if (trim($buf)!=='') $out[]=trim($buf);
        return $out ?: [$text];
    }
}
