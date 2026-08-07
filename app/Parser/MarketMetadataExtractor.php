<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class MarketMetadataExtractor
{
    /** @return array<string,mixed> */
    public function extract(string $segment): array
    {
        $metadata = [];

        if (preg_match('/\b(?:q|r|rq|req(?:uirement)?)\s*([0-9]{1,2})\b/iu', $segment, $match)) {
            $metadata['requirement'] = 'q' . $match[1];
        }

        $attributes = [
            'soul reaping' => ['soul reaping','sr'],
            'fast casting' => ['fast casting','fc'],
            'energy storage' => ['energy storage','estorage','e storage'],
            'channeling magic' => ['channeling magic','channeling'],
            'communing' => ['communing','com'],
            'domination magic' => ['domination magic','domination','dom'],
            'inspiration magic' => ['inspiration magic','inspiration','inspi'],
            'illusion magic' => ['illusion magic','illusion','illus','illu'],
            'divine favor' => ['divine favor','df'],
            'smiting prayers' => ['smiting prayers','smite','smiting'],
            'protection prayers' => ['protection prayers','prot','protection'],
            'curses' => ['curses','curs'],
            'death magic' => ['death magic'],
            'blood magic' => ['blood magic','blood'],
            'spawning power' => ['spawning power','spawning','spaw'],
            'restoration magic' => ['restoration magic','restoration','resto'],
            'healing prayers' => ['healing prayers','healing','heal'],
            'water magic' => ['water magic','water'],
            'fire magic' => ['fire magic','fire'],
            'air magic' => ['air magic','air'],
            'earth magic' => ['earth magic','earth'],
            'strength' => ['strength','str'],
            'leadership' => ['leadership','leader'],
        ];

        foreach ($attributes as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/\b' . preg_quote($alias,'/') . '\b/iu', $segment)) {
                    $metadata['attribute'] = $canonical;
                    if (
                        !isset($metadata['requirement'])
                        && preg_match('/\breq(?:uirement)?\s+' . preg_quote($alias,'/') . '\b/iu', $segment)
                    ) {
                        $metadata['requirement'] = 'any';
                    }
                    break 2;
                }
            }
        }

        if (preg_match('/\b(?:insc|inscr|inscribable|inscriptable)\b/iu', $segment)) {
            $metadata['inscribable'] = true;
        }
        if (preg_match('/\b(?:os|old\s*school)\b/iu', $segment)) {
            $metadata['oldschool'] = true;
        }
        if (preg_match('/\b(?:unded|undedicated)\b/iu', $segment)) {
            $metadata['dedication'] = 'undedicated';
        } elseif (preg_match('/\b(?:ded|dedicated)\b/iu', $segment)) {
            $metadata['dedication'] = 'dedicated';
        }
        if (preg_match('/\bunid(?:entified)?\b/iu', $segment)) {
            $metadata['identified'] = false;
        }

        $upgradePatterns = [
            'zealous' => '/\bzealous\b/iu',
            'vampiric' => '/\b(?:vamp|vampiric)\b/iu',
            'furious' => '/\bfurious\b/iu',
            'of enchanting' => '/\b(?:of\s+enchanting|enchant(?:ing)?\s*\d*%?)\b/iu',
            '+30 health' => '/\+\s*30\s*(?:hp|health)\b/iu',
            'armor +5' => '/\barmor\s*\+\s*5\b/iu',
            'energy +5' => '/\+\s*5\s*energy\b/iu',
            'soul reaping +5' => '/\b(?:sr|soul\s+reaping)\s*\+\s*5\b/iu',
            '15^50' => '/\b15\s*\^\s*50\b/iu',
            '20/20' => '/\b20\s*\/\s*20\b/iu',
            'forget me not' => '/\bforget\s+me\s+not\b/iu',
            'aptitude not attitude' => '/\baptitude\s+not\s+attitude\b/iu',
            'memory' => '/\bmemory(?:\s+of\s+purity)?\b/iu',
        ];

        $upgrades = [];
        foreach ($upgradePatterns as $name => $pattern) {
            if (preg_match($pattern, $segment)) $upgrades[] = $name;
        }
        if ($upgrades !== []) $metadata['upgrades'] = array_values(array_unique($upgrades));

        return $metadata;
    }
}
