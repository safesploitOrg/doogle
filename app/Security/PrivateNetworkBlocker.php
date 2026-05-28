<?php

declare(strict_types=1);

namespace Doogle\Security;

use Closure;

final class PrivateNetworkBlocker
{
    /** @var Closure(string): list<string> */
    private Closure $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = Closure::fromCallable($resolver ?? [self::class, 'resolveHost']);
    }

    public function isBlockedHost(string $host): bool
    {
        $host = strtolower(rtrim(trim($host, "[] \t\n\r\0\x0B"), '.'));

        if ($host === '') {
            return true;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isBlockedIp($host);
        }

        foreach (($this->resolver)($host) as $ip) {
            if ($this->isBlockedIp($ip)) {
                return true;
            }
        }

        return false;
    }

    public function isBlockedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * @return list<string>
     */
    private static function resolveHost(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $ips[] = $record['ip'];
                }

                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            $ipv4 = @gethostbynamel($host);

            if (is_array($ipv4)) {
                $ips = array_merge($ips, $ipv4);
            }
        }

        return array_values(array_unique($ips));
    }
}
