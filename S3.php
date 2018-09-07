<?php

/*
* This file is part of the Pho package.
*
* (c) Emre Sokullu <emre@phonetworks.org>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Pho\Kernel\Services\Storage\Adapters;

use Pho\Kernel\Kernel;
use Pho\Kernel\Services\ServiceInterface;
use Pho\Kernel\Services\Storage\StorageInterface;
use Pho\Kernel\Services\Storage\Exceptions\InaccessiblePathException;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;

/**
* AWS S3 Adapter for Storage
*
* AWS S3 is a simple adapter suitable for cloud based installations (e.g. heroku),
* therefore it **is** scalable. 
*
* @author Emre Sokullu
*/
class S3 implements StorageInterface, ServiceInterface
{

    /**
     * League\Flysystem\Filesystem object through AWS S3 Client adapter
     *
     * @var League\Flysystem\Filesystem
     */
    private $filesystem;
    
    /**
     * Stateful kernel to access services such as Logger.
     *
     * @var Kernel
     */
    private $kernel;
    
    /**
     * Constructor.
     * 
     * @param Kernel $kernel The Pho Kernel to access services
     * @param string $options In Json format, as follows: {"client": {"credentials": {"key", "secret"}, "region", "version"}, "bucket"}
     */
    public function __construct(Kernel $kernel, string $options)
    {
        $this->kernel = $kernel;
        $options = json_decode($options, true);
        $client = new S3Client($options["client"]);
        /*
        if (!file_exists($this->root)&&!mkdir($this->root)) {
            throw new InaccessiblePathException($this->root);
        }
        */
        $this->kernel->logger()->info(
            sprintf("The storage service has started with the %s adapter.", __CLASS__)
        );
        
        $adapter = new AwsS3Adapter($client, $options["bucket"]);
        $this->filesystem = new Filesystem($adapter);
    }
    
    /**
    * {@inheritdoc}
    */
    public function get(string $path): string
    {
        return $this->path_normalize($path);
    }
    
    /**
    * {@inheritdoc}
    */
    public function mkdir(string $dir, bool $recursive = true): void
    {
        if ($this->file_exists($dir)) {
            throw new InaccessiblePathException($dir);
        }
        $this->filesystem->createDir($this->get($dir));
    }
    
    /**
    * {@inheritdoc}
    */
    public function file_exists(string $path): bool
    {
        return $this->filesystem->has($this->get($path));
    }
    
    /**
    * {@inheritdoc}
    */
    public function put(string $file, string $path): void
    {
        $this->filesystem->put($this->get($path), file_get_contents($file));
    }
    
    /**
    * {@inheritdoc}
    */
    public function append(string $file, string $path): void
    {
        $contents = file_get_contents($this->get($path));
        $contents .= file_get_contents($file);
        $this->put($this->get($path), $contents);
    }
    
    
    /**
    * A private method that helps translate directory definition conforming to the operating system settings.
    *
    * @param string $path
    * @return void
    */
    private function path_normalize(string $path): string
    {
        return str_replace("\\", '/', $path);
    }
}
