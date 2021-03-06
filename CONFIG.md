# Configuration



## Resize dimensions

Resize dimensions (such as `300x300`, `640x480!`, `x40`) work exactly the same as Stapler. [Refer this documentation](https://github.com/CodeSleeve/stapler/blob/master/docs/imageprocessing.md) for compatible examples.


## Examples

### Stapler Compatibility

To make it easier to switch between Stapler and Paperclip, Stapler configurations are interpreted and normalized, so they don't need to be rewritten.

```php
<?php
public function __construct(array $attributes = [])
{
    $this->hasAttachedFile('image', [
        'styles'  => [
            'medium' => [
                'dimensions' => '300x300', 
                'auto_orient' => true,
            ],
            'thumb' => '100x100',
        ],
    ]);
    
    // ...
```

This will be internally normalized to auto-orient a medium-sized image, and resize without orienting a thumb-sized image.

This stapler configuration is functionally identical to this paperclip configuration:

```php
<?php
[
    'variants'  => [
        'medium' => [
            'auto-orient' => [],
            'resize'      => ['dimensions' => '300x300'], 
        ],
        'thumb' => [
            'resize' => ['dimensions' => '100x100'],
        ],
    ],
];
```


### Indicating variant extension

Usually variants will keep the original file extension. In some cases, however, you may want to convert files or derive files from originals with a different extension.

In those cases, Paperclip will need to be able to match extensions to variants in order to generate the correct URLs and paths. This can be done in two ways:

- The `extensions` key in the attachment configuration.  
    If specific variants has an alternative extension, this may be indicated as follows. In this example, two variants will keep the same extension, but two custom variants would have a different extension. 
    
```php
<?php
[
    'variants'  => [
        'medium' => '300x300', 
        'thumb' => '100x100',
        'special' => [
            'derive-description-text' => [],    
        ],
        'converted' => [
            'convert-to-bmp' => [],    
        ],
    ],
    'extensions' => [
        'special'   => 'txt',
        'converted' => 'bmp',
    ],
];
```
 
- The `variants` attribute on the parent model of the attachment.  
    The configuration `extensions` approach above will require manually indicating the extensions per variant. 
    This may be automated by enabling the `variants` attribute on the parent model. 
    This is a text column with JSON-encoded information on actually processed variants, which will include the extension for the variant.
    Note that if a variant strategy may result in files with different extensions, this is the only way to allow Paperclip to reliably generate URLs to that variant.   

### Before and After Processing Hooks

To hook into the process of uploading paperclip attached files, set the `before` and/or `after` configuration keys for the attachment. This may be a `callable` anonymous function (not recommended for models that should be serializable!) or a string with a `ClassFQN@methodName` format.

Examples:

```php
<?php
public function __construct(array $attributes = [])
{
    $this->hasAttachedFile('image', [
        'before' => function ($attachment) { /* Do something here */ },
        'after'  => 'YourHook\HelperClass@yourMethodName',
    ]);
    
    // ...
```

The hook method that is called should expect one parameter, which is the current `Czim\Paperclip\Attachment\Attachment` instance being processed (type-hintable interface: `Czim\Paperclip\Attachment\AttachmentInterface`).
