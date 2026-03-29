<?php

declare(strict_types=1);

namespace LaravelNats\Security;

use Illuminate\Contracts\Config\Repository;
use LaravelNats\Security\Exceptions\SubjectNotAllowedException;

/**
 * Optional publish/subscribe allowlists (`nats_basis.acl`).
 */
final class SubjectAclChecker
{
    public function __construct(
        private readonly Repository $config,
    ) {
    }

    /**
     * @throws SubjectNotAllowedException
     */
    public function assertPublishAllowed(string $subject): void
    {
        if (! $this->aclEnabled()) {
            return;
        }

        /** @var list<string>|mixed $allowed */
        $allowed = $this->config->get('nats_basis.acl.allowed_publish_prefixes', []);
        $list = $this->normalizeList($allowed);

        if ($list === []) {
            throw SubjectNotAllowedException::publish($subject);
        }

        if (! SubjectPrefixMatcher::isAllowed($subject, $list)) {
            throw SubjectNotAllowedException::publish($subject);
        }
    }

    /**
     * @throws SubjectNotAllowedException
     */
    public function assertSubscribeAllowed(string $subject): void
    {
        if (! $this->aclEnabled()) {
            return;
        }

        /** @var list<string>|mixed $allowed */
        $allowed = $this->config->get('nats_basis.acl.allowed_subscribe_prefixes', []);
        $list = $this->normalizeList($allowed);

        if ($list === []) {
            throw SubjectNotAllowedException::subscribe($subject);
        }

        if (! SubjectPrefixMatcher::isAllowed($subject, $list)) {
            throw SubjectNotAllowedException::subscribe($subject);
        }
    }

    private function aclEnabled(): bool
    {
        return filter_var($this->config->get('nats_basis.acl.enabled', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * @return list<string>
     */
    private function normalizeList(mixed $allowed): array
    {
        if (! is_array($allowed)) {
            return [];
        }

        $out = [];
        foreach ($allowed as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
        }

        return $out;
    }
}
