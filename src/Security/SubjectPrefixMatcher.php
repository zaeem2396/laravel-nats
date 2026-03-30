<?php

declare(strict_types=1);

namespace LaravelNats\Security;

/**
 * Matches a NATS subject against a list of allowed prefixes / exact names (see `nats_basis.acl`).
 */
final class SubjectPrefixMatcher
{
    /**
     * @param list<string> $allowed
     */
    public static function isAllowed(string $subject, array $allowed): bool
    {
        if ($subject === '') {
            return false;
        }

        foreach ($allowed as $entry) {
            if (! is_string($entry)) {
                continue;
            }
            $p = trim($entry);
            if ($p === '') {
                continue;
            }

            if ($subject === $p) {
                return true;
            }

            if (str_ends_with($p, '.')) {
                if (str_starts_with($subject, $p)) {
                    return true;
                }

                continue;
            }

            if (str_starts_with($subject, $p . '.')) {
                return true;
            }
        }

        return false;
    }
}
