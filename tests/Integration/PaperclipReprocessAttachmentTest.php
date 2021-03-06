<?php
namespace Czim\Paperclip\Test\Integration;

use Czim\FileHandling\Contracts\Storage\StorableFileFactoryInterface;
use Czim\FileHandling\Storage\File\SplFileInfoStorableFile;
use Czim\Paperclip\Test\Helpers\VariantStrategies\TestTextToHtmlStrategy;
use Czim\Paperclip\Test\ProvisionedTestCase;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use SplFileInfo;

class PaperclipReprocessAttachmentTest extends ProvisionedTestCase
{

    /**
     * @test
     */
    function it_skips_reprocessing_a_variant_if_attachment_is_not_filled()
    {
        $model = $this->getTestModel();

        static::assertFalse($model->image->isFilled());

        $model->image->reprocess();
    }

    /**
     * @test
     */
    function it_reprocesses_variant()
    {
        $model = $this->getTestModel();

        $model->image = new SplFileInfo($this->getTestFilePath('empty.gif'));
        $model->save();

        static::assertTrue($model->image->isFilled());
        static::assertEquals('empty.gif', $model->image_file_name);

        $processedFilePath = $this->getUploadedAttachmentPath($model, 'empty.gif', 'image', 'medium');

        static::assertFileExists($processedFilePath, 'Variant file does not exist');


        // Delete the uploaded file, so we can see if it gets rewritten on reprocessing
        unlink($processedFilePath);
        static::assertFileNotExists($processedFilePath, 'Variant file should not exist after unlinking');


        $this->prepareMockSetupForReprocessingSource($model);

        $model->image->reprocess();

        static::assertFileExists($processedFilePath, 'Variant file does not exist after refresh');
    }

    /**
     * @test
     */
    function it_processes_a_variant_updating_the_model_variants_attribute()
    {
        $this->app['config']->set('paperclip.variants.aliases.test-html', TestTextToHtmlStrategy::class);

        $model = $this->getTestModelWithAttachmentConfig([
            'attributes' => [
                'variants' => true,
            ],
            'variants' => [
                'test' => [
                    'test-html' => [],
                ],
            ],
        ]);

        $model->attachment = new SplFileInfo($this->getTestFilePath('empty.gif'));
        $model->save();

        $expectedVariantsInformation = [
            'test' => [ 'ext' => 'htm', 'type' => 'text/html' ]
        ];

        static::assertEquals($expectedVariantsInformation, $model->attachment->variantsAttribute());

        $model->attachment_variants = null;
        $model->save();

        static::assertEmpty($model->attachment->variantsAttribute(), 'Variants should be empty for test');

        $this->prepareMockSetupForReprocessingSource($model, 'attachment');

        // Test
        $model->attachment->reprocess();

        static::assertEquals(
            $expectedVariantsInformation,
            $model->attachment->variantsAttribute(),
            'Variant information not rewritten after reprocessing'
        );
    }
    
    /**
     * @test
     * @expectedException \Czim\Paperclip\Exceptions\VariantProcessFailureException
     * @expectedExceptionMessageRegExp #failed to process variant 'medium'#i
     */
    function it_throws_an_exception_when_something_goes_wrong_while_reprocessing_a_variant()
    {
        $model = $this->getTestModel();

        $model->image = new SplFileInfo($this->getTestFilePath('empty.gif'));
        $model->save();

        $processedFilePath = $this->getUploadedAttachmentPath($model, 'empty.gif', 'image');

        static::assertFileExists($processedFilePath, 'Original file does not exist');

        // Delete the original file, so reprocessing fails
        unlink($processedFilePath);
        static::assertFileNotExists($processedFilePath, 'File should not exist after unlinking');

        $this->prepareMockSetupForReprocessingException($model);

        $model->image->reprocess();
    }


    /**
     * @param Model  $model
     * @param string $attachment
     * @param bool   $withExpection
     */
    protected function prepareMockSetupForReprocessingSource(Model $model, $attachment = 'image', $withExpection = true)
    {
        /** @var Mockery\MockInterface|Mockery\Mock|StorableFileFactoryInterface $factory */
        $factory = Mockery::mock(StorableFileFactoryInterface::class);
        $source = $this->getSourceForReprocessing($this->getTestFilePath('empty.gif'));

        if ($withExpection) {
            $factory->shouldReceive('makeFromUrl')
                ->once()
                ->with($model->{$attachment}->url(), 'empty.gif', 'image/gif')
                ->andReturn($source);
        } else {
            $factory->shouldReceive('makeFromUrl')
                ->with($model->{$attachment}->url(), 'empty.gif', 'image/gif')
                ->andReturn($source);
        }

        app()->instance(StorableFileFactoryInterface::class, $factory);
    }

    /**
     * @param Model  $model
     * @param string $attachment
     */
    protected function prepareMockSetupForReprocessingException(Model $model, $attachment = 'image')
    {
        /** @var Mockery\MockInterface|Mockery\Mock|StorableFileFactoryInterface $factory */
        $factory = Mockery::mock(StorableFileFactoryInterface::class);
        $source = $this->getSourceForReprocessing($this->getTestFilePath('does_not_exist.gif'));

        $factory->shouldReceive('makeFromUrl')
            ->once()
            ->with($model->{$attachment}->url(), 'empty.gif', 'image/gif')
            ->andReturn($source);

        app()->instance(StorableFileFactoryInterface::class, $factory);
    }

    /**
     * @param string $path
     * @param string $name
     * @return SplFileInfoStorableFile
     */
    protected function getSourceForReprocessing($path, $name = 'empty.gif')
    {
        $source = new SplFileInfoStorableFile();
        $source->setData(new SplFileInfo($path));
        $source->setName($name);

        return $source;
    }

}
