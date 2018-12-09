<?php
namespace f2r;

use f2r\ResilientCache\ResilientCache;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class ResilientCacheTest extends TestCase
{
    public function testNotCached()
    {
        $adapter = $this->createMock(CacheInterface::class);
        $adapter->expects($this->once())->method('has')->willReturn(false);
        $adapter->expects($this->never())->method('get');
        $adapter->expects($this->once())->method('set')->with('a-key', [10005, 'foo'], null)->willReturn(true);

        $cache = new ResilientCache($adapter);
        $cache->setTime(10000);
        $value = $cache->that(function() {
            return 'foo';
        }, 'a-key', 5);

        $this->assertEquals('foo', $value);

        $adapter = $this->createMock(CacheInterface::class);
        $adapter->expects($this->once())->method('has')->willReturn(false);
        $adapter->expects($this->never())->method('get');
        $adapter->expects($this->once())->method('set')->with('a-key', [10005, 'foo'], 100)->willReturn(true);

        $cache = new ResilientCache($adapter, 100);
        $cache->setTime(10000);
        $value = $cache->that(function() {
            return 'foo';
        }, 'a-key', 5);

        $this->assertEquals('foo', $value);
    }

    public function testCached()
    {
        $adapter = $this->createMock(CacheInterface::class);
        $adapter->expects($this->once())->method('has')->willReturn(true);
        $adapter->expects($this->once())->method('get')->with('a-key', null)->willReturn([100000, 'bar']);
        $adapter->expects($this->never())->method('set');

        $cache = new ResilientCache($adapter);
        $cache->setTime(100000);
        $value = $cache->that(function() {
            $this->assertTrue(false, 'callable should not been called');
        }, 'a-key', 5);

        $this->assertEquals('bar', $value);
    }

    public function testCachedExpired()
    {
        $adapter = $this->createMock(CacheInterface::class);
        $adapter->expects($this->once())->method('has')->willReturn(true);
        $adapter->expects($this->once())->method('get')->with('a-key', null)->willReturn([100000, 'bar']);
        $adapter->expects($this->once())->method('set')->with('a-key', [100006, 'foo-bar'], null)->willReturn(true);

        $cache = new ResilientCache($adapter);
        $cache->setTime(100001);
        $value = $cache->that(function() {
            return 'foo-bar';
        }, 'a-key', 5);

        $this->assertEquals('foo-bar', $value);
    }

    public function testCachedResilient()
    {
        $adapter = $this->createMock(CacheInterface::class);
        $adapter->expects($this->once())->method('has')->willReturn(true);
        $adapter->expects($this->once())->method('get')->with('a-key', null)->willReturn([100000, 'bar']);
        $adapter->expects($this->never())->method('set');

        $cache = new ResilientCache($adapter);
        $cache->setTime(100001);
        $value = $cache->that(function() {
            throw new \Exception('failed');
        }, 'a-key', 5);

        $this->assertEquals('bar', $value);
    }

    public function testCachedResilientAndRetryTtl()
    {
        $adapter = $this->createMock(CacheInterface::class);
        $adapter->expects($this->once())->method('has')->willReturn(true);
        $adapter->expects($this->once())->method('get')->with('a-key', null)->willReturn([100000, 'bar']);
        $adapter->expects($this->once())->method('set')->with('a-key', [100003, 'bar'], null)->willReturn(true);

        $cache = new ResilientCache($adapter, null, 2);
        $cache->setTime(100001);
        $value = $cache->that(function() {
            throw new \Exception('failed');
        }, 'a-key', 5);

        $this->assertEquals('bar', $value);
    }

}