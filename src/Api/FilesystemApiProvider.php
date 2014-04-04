<?php
namespace Aws\Api;

/**
 * Provides service descriptions from a directory structure.
 */
class FilesystemApiProvider implements ApiProviderInterface
{
    /** @var string */
    private $path;

    /** @var string */
    private $apiSuffix;

    /**
     * @param string $path Path to the service descriptions on disk
     * @param bool   $min  Set to true to load minified models
     */
    public function __construct($path, $min = false)
    {
        $this->path = rtrim($path, '/\\');
        $this->apiSuffix = $min ? '.normal.min.json' : '.normal.json';
    }

    public function getService($service, $version)
    {
        if ($version == 'latest') {
            if ($versions = $this->getServiceVersions($service)) {
                sort($versions);
                $version = $versions[0];
            }
        }

        $path = $this->getPath($service, $version, $this->apiSuffix);

        return file_exists($path)
            ? new Service($this->parseJson(file_get_contents($path)))
            : null;
    }

    public function getServiceNames()
    {
        $files = $this->getServiceFiles($this->apiSuffix);
        $search = [$this->path, $this->apiSuffix];
        $results = [];

        foreach ($files as $f) {
            $results[explode('-', str_replace($search, '', $f))[0]] = true;
        }

        return array_keys($results);
    }

    public function getServiceVersions($service)
    {
        $files = $this->getServiceFiles($this->apiSuffix);
        $search = [$this->path, $this->apiSuffix];
        $results = [];

        foreach ($files as $f) {
            if (strpos($f, $service) === 0) {
                $results[] = explode('-', str_replace($search, '', $f), 2)[1];
            }
        }

        return $results;
    }

    public function getServicePaginatorConfig($service, $version)
    {
        $path = $this->getPath($service, $version, '.paginators.json');

        return file_exists($path)
            ? $this->parseJson(file_get_contents($path))
            : [];
    }

    public function getServiceWaiterConfig($service, $version)
    {
        $path = $this->getPath($service, $version, '.waiters.json');

        return file_exists($path)
            ? $this->parseJson(file_get_contents($path))
            : [];
    }

    private function getPath($service, $version, $extension)
    {
        return "{$this->path}/{$service}-{$version}{$extension}";
    }

    private function getServiceFiles($suffix)
    {
        $services = [];
        $len = -1 * strlen($suffix);

        foreach (scandir($this->path) as $file) {
            if (substr($file, $len) == $suffix) {
                $services[] = $file;
            }
        }

        return $services;
    }

    private function parseJson($json)
    {
        $data = json_decode($json, true);
        if (!json_last_error()) {
            return $data;
        }

        throw new \RuntimeException('Error parsing JSON: ' . json_last_error());
    }
}