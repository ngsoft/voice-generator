<?php

namespace Service;

use Symfony\Component\HttpFoundation\Request;

class AccessService
{
    /**
     * @phan-suppress PhanTypeMismatchReturnNullable
     */
    public function getRequestIp(Request $request): string
    {
        $ip = $request->getClientIp();

        /* @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Forwarded-For */
        if ($forwarded = $request->headers->get('x-forwarded-for', ''))
        {
            $ips = array_filter(preg_split('#[ ,]+#', $forwarded));

            // last proxy request ip
            $ip  = end($ips);
        }

        return $ip;
    }

    public function doCheckAcl(Request $request, string $rules): bool
    {
        if (empty($rules))
        {
            return false;
        }
        // remove whitespaces from a list
        $controlList = array_filter(explode(',', $rules), fn ($str) => ! empty(trim($str)));

        $client      = $this->getRequestIp($request);

        foreach ($controlList as $ip)
        {
            $ip     = trim($ip);
            $result = true;

            // ban ip starting with '!'
            if (str_starts_with($ip, '!'))
            {
                // reversed result if matches
                $result = false;
                $ip     = substr($ip, 1);
            }

            // check ip ranges
            if (str_starts_with($ip, '^'))
            {
                $ip = substr($ip, 1);

                if (str_starts_with($client, $ip))
                {
                    return $result;
                }

                continue;
            }

            // check exact match

            if ($ip === $client)
            {
                return $result;
            }
        }

        // checks if "*" in the list (after banned ips)
        if (in_array('*', $controlList))
        {
            return true;
        }

        return false;
    }
}
