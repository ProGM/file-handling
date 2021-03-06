<?php
namespace Czim\FileHandling\Test\Unit\Handler;

use Czim\FileHandling\Contracts\Storage\PathHelperInterface;
use Czim\FileHandling\Contracts\Storage\StorableFileInterface;
use Czim\FileHandling\Contracts\Storage\StorageInterface;
use Czim\FileHandling\Contracts\Storage\StoredFileInterface;
use Czim\FileHandling\Contracts\Variant\VariantProcessorInterface;
use Czim\FileHandling\Handler\FileHandler;
use Czim\FileHandling\Test\TestCase;
use Mockery;

class FileHandlerTest extends TestCase
{

    public function tearDown()
    {
        Mockery::close();
    }

    /**
     * @test
     */
    function it_processes_a_storable_file_without_variants()
    {
        $storage   = $this->getMockStorage();
        $processor = $this->getMockVariantProcessor();
        $helper    = $this->getMockPathHelper();

        $helper->shouldReceive('addVariantToBasePath')
            ->with('test/target/path')
            ->once()
            ->andReturn('test/target/path/original');

        $storedMock = $this->getMockStoredFile();
        $storage->shouldReceive('store')
            ->with(Mockery::type(StorableFileInterface::class), 'test/target/path/original')
            ->once()
            ->andReturn($storedMock);

        $handler = new FileHandler($storage, $processor, $helper);

        $file = $this->getMockStorableFile();

        $stored = $handler->process($file, 'test/target/path', ['test' => true]);

        static::assertInternalType('array', $stored);
        static::assertCount(1, $stored);

        static::assertInstanceOf(StoredFileInterface::class, $stored[ FileHandler::ORIGINAL ]);
        static::assertSame($storedMock, $stored[ FileHandler::ORIGINAL ]);
    }

    /**
     * @test
     */
    function it_processes_a_storable_file_with_variants()
    {
        $storage   = $this->getMockStorage();
        $processor = $this->getMockVariantProcessor();
        $helper    = $this->getMockPathHelper();

        $file           = $this->getMockStorableFile();
        $storedMock     = $this->getMockStoredFile();
        $storedTinyMock = $this->getMockStoredFile();

        $tinyVariantConfig = [
            'resize'     => ['dimensions' => '10x10'],
            'autoorient' => [],
        ];

        $helper->shouldReceive('addVariantToBasePath')
            ->with('test/target/path')
            ->once()
            ->andReturn('test/target/path/original');

        $helper->shouldReceive('addVariantToBasePath')
            ->with('test/target/path', 'tiny')
            ->once()
            ->andReturn('test/target/path/tiny');

        $processor->shouldReceive('process')
            ->with($file, 'tiny', $tinyVariantConfig)
            ->once()
            ->andReturn($storedTinyMock);

        $storage->shouldReceive('store')
            ->with(Mockery::type(StorableFileInterface::class), 'test/target/path/original')
            ->once()
            ->andReturn($storedMock);

        $storage->shouldReceive('store')
            ->with(Mockery::type(StorableFileInterface::class), 'test/target/path/tiny')
            ->once()
            ->andReturn($storedTinyMock);

        $handler = new FileHandler($storage, $processor, $helper);

        $stored = $handler->process($file, 'test/target/path', [
            FileHandler::CONFIG_VARIANTS => [
                'tiny' => $tinyVariantConfig,
            ],
        ]);

        static::assertInternalType('array', $stored);
        static::assertCount(2, $stored);

        static::assertInstanceOf(StoredFileInterface::class, $stored[ FileHandler::ORIGINAL ]);
        static::assertSame($storedMock, $stored[ FileHandler::ORIGINAL ]);
        static::assertInstanceOf(StoredFileInterface::class, $stored['tiny']);
        static::assertSame($storedTinyMock, $stored['tiny']);
    }

    /**
     * @test
     */
    function it_processes_a_single_file_variant()
    {
        $storage   = $this->getMockStorage();
        $processor = $this->getMockVariantProcessor();
        $helper    = $this->getMockPathHelper();

        $file           = $this->getMockStorableFile();
        $storedMock     = $this->getMockStoredFile();

        $tinyVariantConfig = [
            'resize' => ['dimensions' => '10x10'],
        ];

        $helper->shouldReceive('addVariantToBasePath')
            ->with('test/target/path', 'tiny')
            ->once()
            ->andReturn('test/target/path/tiny');

        $processor->shouldReceive('process')
            ->with($file, 'tiny', $tinyVariantConfig)
            ->once()
            ->andReturn($storedMock);

        $storage->shouldReceive('store')
            ->with(Mockery::type(StorableFileInterface::class), 'test/target/path/tiny')
            ->once()
            ->andReturn($storedMock);

        $handler = new FileHandler($storage, $processor, $helper);

        $stored = $handler->processVariant($file, 'test/target/path', 'tiny', $tinyVariantConfig);

        static::assertInstanceOf(StoredFileInterface::class, $stored);
        static::assertSame($storedMock, $stored);
    }

    /**
     * @test
     */
    function it_returns_variant_urls_for_a_basepath_and_list_of_variants()
    {
        $storage   = $this->getMockStorage();
        $processor = $this->getMockVariantProcessor();
        $helper    = $this->getMockPathHelper();

        $helper->shouldReceive('addVariantToBasePath')
            ->with('test/target/path', 'tiny')
            ->once()
            ->andReturn('test/target/path/tiny');

        $helper->shouldReceive('addVariantToBasePath')
            ->with('test/target/path', FileHandler::ORIGINAL)
            ->once()
            ->andReturn('test/target/path/original');

        $storage->shouldReceive('url')
            ->with('test/target/path/tiny/test.gif')
            ->andReturn('http://test.com/test/target/path/tiny/test.gif');

        $storage->shouldReceive('url')
            ->with('test/target/path/original/test.gif')
            ->andReturn('http://test.com/test/target/path/original/test.gif');

        $handler = new FileHandler($storage, $processor, $helper);

        $urls = $handler->variantUrlsForBasePath('test/target/path', 'test.gif', ['tiny']);

        static::assertEquals([
            'original' => 'http://test.com/test/target/path/original/test.gif',
            'tiny'     => 'http://test.com/test/target/path/tiny/test.gif',
        ], $urls);
    }

    /**
     * @test
     */
    function it_returns_variant_urls_for_a_stored_file_and_a_list_of_variants()
    {
        $storage   = $this->getMockStorage();
        $processor = $this->getMockVariantProcessor();
        $helper    = $this->getMockPathHelper();

        $storedMock = $this->getMockStoredFile();

        $storedMock->shouldReceive('path')->once()->andReturn('test/target/path/tiny');
        $storedMock->shouldReceive('name')->once()->andReturn('test.gif');

        $helper->shouldReceive('basePath')
            ->once()
            ->with('test/target/path/tiny')
            ->andReturn('test/target/path');

        $helper->shouldReceive('addVariantToBasePath')
            ->with('test/target/path', 'tiny')
            ->once()
            ->andReturn('test/target/path/tiny');

        $helper->shouldReceive('addVariantToBasePath')
            ->with('test/target/path', FileHandler::ORIGINAL)
            ->once()
            ->andReturn('test/target/path/original');

        $storage->shouldReceive('url')
            ->with('test/target/path/tiny/test.gif')
            ->andReturn('http://test.com/test/target/path/tiny/test.gif');

        $storage->shouldReceive('url')
            ->with('test/target/path/original/test.gif')
            ->andReturn('http://test.com/test/target/path/original/test.gif');

        $handler = new FileHandler($storage, $processor, $helper);

        $urls = $handler->variantUrlsForStoredFile($storedMock, ['tiny']);

        static::assertEquals([
            'original' => 'http://test.com/test/target/path/original/test.gif',
            'tiny'     => 'http://test.com/test/target/path/tiny/test.gif',
        ], $urls);
    }

    /**
     * @test
     */
    function it_deletes_variants_and_the_original_for_a_file()
    {
        $storage   = $this->getMockStorage();
        $processor = $this->getMockVariantProcessor();
        $helper    = $this->getMockPathHelper();

        $helper->shouldReceive('addVariantToBasePath')
            ->with('test/target/path', 'tiny')
            ->once()
            ->andReturn('test/target/path/tiny');

        $helper->shouldReceive('addVariantToBasePath')
            ->with('test/target/path', FileHandler::ORIGINAL)
            ->once()
            ->andReturn('test/target/path/original');

        $storage->shouldReceive('exists')
            ->with('test/target/path/tiny/test.gif')
            ->once()
            ->andReturn(false);

        $storage->shouldReceive('exists')
            ->with('test/target/path/original/test.gif')
            ->once()
            ->andReturn(true);

        $storage->shouldReceive('delete')
            ->with('test/target/path/original/test.gif')
            ->once()
            ->andReturn(true);

        $handler = new FileHandler($storage, $processor, $helper);

        static::assertTrue($handler->delete('test/target/path', 'test.gif', ['tiny']));
    }

    /**
     * @test
     */
    function it_deletes_a_single_variant_using_a_full_variant_path()
    {
        $storage   = $this->getMockStorage();
        $processor = $this->getMockVariantProcessor();
        $helper    = $this->getMockPathHelper();

        $storage->shouldReceive('exists')
            ->with('test/target/path/tiny/test.gif')
            ->once()
            ->andReturn(true);

        $storage->shouldReceive('delete')
            ->with('test/target/path/tiny/test.gif')
            ->once()
            ->andReturn(true);

        $handler = new FileHandler($storage, $processor, $helper);

        static::assertTrue($handler->deleteVariant('test/target/path/tiny/test.gif'));
    }

    /**
     * @test
     */
    function it_deletes_a_single_variant_using_basepath_and_partial_parameters()
    {
        $storage   = $this->getMockStorage();
        $processor = $this->getMockVariantProcessor();
        $helper    = $this->getMockPathHelper();

        $helper->shouldReceive('addVariantToBasePath')
            ->with('test/target/path', 'tiny')
            ->once()
            ->andReturn('test/target/path/tiny');

        $storage->shouldReceive('exists')
            ->with('test/target/path/tiny/test.gif')
            ->once()
            ->andReturn(true);

        $storage->shouldReceive('delete')
            ->with('test/target/path/tiny/test.gif')
            ->once()
            ->andReturn(true);

        $handler = new FileHandler($storage, $processor, $helper);

        static::assertTrue($handler->deleteVariant('test/target/path', 'tiny', 'test.gif'));
    }

    /**
     * @test
     * @expectedException \UnexpectedValueException
     */
    function it_throws_an_exception_when_trying_to_delete_a_variant_with_incomplete_parameters()
    {
        $handler = new FileHandler($this->getMockStorage(), $this->getMockVariantProcessor(), $this->getMockPathHelper());

        $handler->deleteVariant('some/path', 'variant');
    }


    /**
     * @return Mockery\MockInterface|StorageInterface
     */
    protected function getMockStorage()
    {
        return Mockery::mock(StorageInterface::class);
    }

    /**
     * @return Mockery\MockInterface|VariantProcessorInterface
     */
    protected function getMockVariantProcessor()
    {
        return Mockery::mock(VariantProcessorInterface::class);
    }

    /**
     * @return Mockery\MockInterface|PathHelperInterface
     */
    protected function getMockPathHelper()
    {
        return Mockery::mock(PathHelperInterface::class);
    }

    /**
     * @return Mockery\MockInterface|StorableFileInterface
     */
    protected function getMockStorableFile()
    {
        return Mockery::mock(StorableFileInterface::class);
    }

    /**
     * @return Mockery\MockInterface|StoredFileInterface
     */
    protected function getMockStoredFile()
    {
        return Mockery::mock(StoredFileInterface::class);
    }

}
