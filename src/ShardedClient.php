<?php

namespace Aternos\Etcd;

use Aternos\Etcd\Exception\InvalidClientException;
use Etcdserverpb\Compare;
use Etcdserverpb\RequestOp;
use Flexihash\Flexihash;

/**
 * Class ShardedClient
 *
 * @package Aternos\Etcd
 */
class ShardedClient implements ClientInterface
{
    /**
     * @var ClientInterface[]
     */
    protected $clients = [];

    /**
     * @var ClientInterface[]
     */
    protected $keyCache = [];

    /**
     * @var Flexihash
     */
    protected $hash = null;

    /**
     * ShardedClient constructor.
     *
     * @param ClientInterface[] $clients
     * @throws InvalidClientException
     */
    public function __construct(array $clients)
    {
        foreach ($clients as $client) {
            if (!$client instanceof ClientInterface) {
                throw new InvalidClientException("Invalid client in client list.");
            }

            $this->clients[$client->getHostname()] = $client;
        }
    }

    /**
     * Get the correct client object for that key through consistent hashing
     *
     * @param string $key
     * @return ClientInterface
     * @throws \Flexihash\Exception
     */
    protected function getClientFromKey(string $key): ClientInterface
    {
        if (isset($this->keyCache[$key])) {
            return $this->keyCache[$key];
        }

        if ($this->hash === null) {
            $this->hash = new Flexihash();
            foreach ($this->clients as $client) {
                $this->hash->addTarget($client->getHostname());
            }
        }

        $clientHostname = $this->hash->lookup($key);
        $this->keyCache[$key] = $this->clients[$clientHostname];
        return $this->keyCache[$key];
    }

    /**
     * @inheritDoc
     * @throws \Flexihash\Exception
     */
    public function getHostname(?string $key = null): string
    {
        if ($key) {
            return $this->getClientFromKey($key)->getHostname($key);
        }
        return implode("-", array_keys($this->clients));
    }

    /**
     * @inheritDoc
     * @throws \Flexihash\Exception
     */
    public function put(string $key, $value, bool $prevKv = false, int $lease = 0, bool $ignoreLease = false, bool $ignoreValue = false)
    {
        return $this->getClientFromKey($key)->put($key, $value, $prevKv, $lease, $ignoreLease, $ignoreValue);
    }

    /**
     * @inheritDoc
     * @throws \Flexihash\Exception
     */
    public function get(string $key)
    {
        return $this->getClientFromKey($key)->get($key);
    }

    /**
     * @inheritDoc
     * @throws \Flexihash\Exception
     */
    public function delete(string $key)
    {
        return $this->getClientFromKey($key)->delete($key);
    }

    /**
     * @inheritDoc
     * @throws \Flexihash\Exception
     */
    public function putIf(string $key, string $value, $compareValue, bool $returnNewValueOnFail = false)
    {
        return $this->getClientFromKey($key)->putIf($key, $value, $compareValue, $returnNewValueOnFail);
    }

    /**
     * @inheritDoc
     * @throws \Flexihash\Exception
     */
    public function deleteIf(string $key, $compareValue, bool $returnNewValueOnFail = false)
    {
        return $this->getClientFromKey($key)->deleteIf($key, $compareValue, $returnNewValueOnFail);
    }

    /**
     * @inheritDoc
     * @throws \Flexihash\Exception
     */
    public function requestIf(string $key, RequestOp $requestOperation, Compare $compare, bool $returnNewValueOnFail = false)
    {
        return $this->getClientFromKey($key)->requestIf($key, $requestOperation, $compare, $returnNewValueOnFail);
    }

    /**
     * @inheritDoc
     * @throws \Flexihash\Exception
     */
    public function getCompare(string $key, string $value, int $result, int $target): Compare
    {
        return $this->getClientFromKey($key)->getCompare($key, $value, $result, $target);
    }

    /**
     * @inheritDoc
     * @throws \Flexihash\Exception
     */
    public function getGetOperation(string $key): RequestOp
    {
        return $this->getClientFromKey($key)->getGetOperation($key);
    }

    /**
     * @inheritDoc
     * @throws \Flexihash\Exception
     */
    public function getPutOperation(string $key, string $value): RequestOp
    {
        return $this->getClientFromKey($key)->getPutOperation($key, $value);
    }

    /**
     * @inheritDoc
     * @throws \Flexihash\Exception
     */
    public function getDeleteOperation(string $key): RequestOp
    {
        return $this->getClientFromKey($key)->getDeleteOperation($key);
    }
}