<?php

namespace TwkDj\FlickrOrganizer;

require __DIR__ . '/../vendor/autoload.php';

class Organizer
{
    private $config;
    private $configPath;
    private $albums;
    private $mediaAlbumMapping = [];
    private $mediaFiles;
    private $options           = [
        'dryrun'  => false,     // True, dry run 不會真的建目錄、複製/移動檔案
        'move'    => false,    // 移動檔案，不是 copy
        'silence' => false,    // 是否顯示過程資訊
    ];

    private $createdFolders = [];

    public function execute()
    {
        $this->initAlbums();
        $this->initMediaFiles($this->config->mediaDir);

        foreach ($this->mediaFiles as $file) {
            $albumNames = $this->getMediaAlbumNames($file) ?: ['Flickr_Others'];
            if ($this->options['move']) {
                $this->mediaToAlbumFolder('move', $file, $albumNames);
            } else {
                $this->mediaToAlbumFolder('copy', $file, $albumNames);
            }
            ob_flush(); // 輸出 buffer 的 log 結果
        }
    }

    private function initAlbums()
    {
        $this->albums = $this->getAlbums();
        $this->buildMediaAlbumMapping();
    }

    /**
     * @return array
     */
    private function getAlbums()
    {
        $file = $this->config->baseDir . '/albums.json';
        if (is_file($file)) {
            $jsonResult = json_decode(file_get_contents($file));
            if ( ! isset($jsonResult->albums)) {
                throw new \RuntimeException("Failed to parse albums.json to get albums info");
            }

            return $jsonResult->albums;
        }

        throw new \RuntimeException(
            sprintf('Failed to access albums.json from baseDir(%s)', $this->config->baseDir)
        );
    }

    private function buildMediaAlbumMapping()
    {
        foreach ($this->albums as $index => $album) {
            array_map(function ($mediaId) use ($index) {
                $this->pushMediaIdIntoMapping($mediaId, $index);
            }, $album->photos);
        }
    }

    private function pushMediaIdIntoMapping($mediaId, $index)
    {
        $index = (int)$index;

        if ( ! array_key_exists($mediaId, $this->mediaAlbumMapping)) {
            $this->mediaAlbumMapping[$mediaId] = [$index];
        }

        if (in_array($index, $this->mediaAlbumMapping[$mediaId], true)) {
            return;
        }

        $this->mediaAlbumMapping[$mediaId][] = $index;
    }

    /**
     * 找出所有 mediaDir 目錄下的圖檔/影片 jpg, mp4, mov 先這三種吧，會遞迴進入子目錄中。
     */
    private function initMediaFiles($dir)
    {
        $this->mediaFiles = [];
        $this->listMediaFiles($dir);
    }

    private function listMediaFiles($dir)
    {
        $dir = rtrim($dir, '/');
        foreach (array_filter(glob($dir . '/*'), 'is_dir') as $dir) {
            $this->listMediaFiles($dir);
        }

        $mediaFiles = array_filter(glob($dir . '/{*.jpg,*.mov,*.mp4}', GLOB_BRACE), 'is_file');
        if (empty($mediaFiles)) {
            return;
        }

        $this->mediaFiles = array_merge($this->mediaFiles, $mediaFiles);
    }

    /**
     * @return mixed
     */
    public function getConfigPath()
    {
        return $this->configPath;
    }

    /**
     * @param $configPath
     */
    public function setConfigPath($configPath)
    {
        $this->configPath = $configPath;

        if ( ! is_file($configPath)) {
            throw new \RuntimeException(
                'Config.json not exists! Copy config.json.example to config.json, and adjust accordingly'
            );
        }

        $this->setConfig();

        return $this;
    }

    private function setConfig()
    {
        $this->config = json_decode(file_get_contents($this->getConfigPath()));

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Invalid config file format, not json format: ' . json_last_error_msg()
            );
        }

        if ( ! isset($this->config->baseDir) || ! is_dir($this->config->baseDir)) {
            throw new \RuntimeException(
                'You should set up "baseDir" value to a valid directory which stores account data from Flickr'
            );
        }

        if ( ! isset($this->config->mediaDir) || ! is_dir($this->config->mediaDir)) {
            throw new \RuntimeException(
                'You should set up "mediaDir" value to a valid directory which stores media jpgs from Flickr'
            );
        }
    }

    /**
     * 一個檔案可能存在多個 album 之下，所以回傳 album title 陣列
     *
     * @param $file
     * @return array|null
     */
    private function getMediaAlbumNames($file)
    {
        $filename = $this->baseName($file);
        $possibleIds = array_values(array_filter(explode('_', $filename), function ($token) {
            return is_numeric($token);
        }));

        foreach ($possibleIds as $id) {
            if (isset($this->mediaAlbumMapping[$id])) {
                return $this->albumTitles($this->mediaAlbumMapping[$id]);
            }
        }

        return null;
    }

    private function albumTitles(array $albumIds)
    {
        return array_map(function ($albumId) {
            return $this->cleanTitleAsFolderName($this->albums[$albumId]->title);
        }, $albumIds);
    }

    /**
     * @param $title
     * @return mixed
     */
    private function cleanTitleAsFolderName($title)
    {
        return str_replace('/', '_', $title);
    }

    private function mediaToAlbumFolder($operation, $file, array $albumNames)
    {
        foreach ($albumNames as $albumName) {
            $folderPath = $this->createAlbumFolder($albumName);

            $success = $this->operateFile($operation, $file, $folderPath);
            if ( ! $success) {
                throw new \RuntimeException(
                    sprintf("Failed to %s %s into folder %s\n", $operation, $file, $folderPath)
                );
            }
            $this->echo("%s %s into %s\n", ucfirst($operation), $file, $folderPath);
        }
    }

    private function operateFile($operation, $file, string $folderPath)
    {
        if ($this->options['dryrun']) {
            return true;
        }

        $destination = rtrim($folderPath, '/') . '/' . $this->baseName($file);
        switch ($operation) {
            case 'copy':
                return copy($file, $destination);
            case 'move':
                return rename($file, $destination);
            default:
                throw new \RuntimeException('Please specify which operation to execute');
        }
    }

    /**
     * @param bool $isMove
     * @return Organizer
     */
    public function setIsMove(bool $isMove)
    {
        $this->options['move'] = $isMove;

        return $this;
    }

    private function createAlbumFolder($albumName)
    {
        $folderPath = rtrim($this->config->outputDir, '/') . '/' . $albumName;

        if ($this->isValidDirectory($folderPath)) {
            if ( ! in_array($folderPath, $this->createdFolders, true)) {
                $this->echo("Folder %s exists, won't create new one\n", $folderPath);
                $this->createdFolders[] = $folderPath;
            }

            return $folderPath;
        }

        if ( ! $this->options['dryrun']) {
            $success = mkdir($folderPath, 0777, true);
            if ( ! $success) {
                throw new \RuntimeException(sprintf('Failed to create folder %s', $folderPath));
            };
        }

        $this->echo("Created folder %s\n", $folderPath);

        return $folderPath;
    }

    /**
     * ref: https://www.codezuzu.com/2015/03/how-to-check-if-directory-exists-in-php/
     *
     * @param $path
     * @return bool
     */
    public function isValidDirectory($path)
    {
        // Clears file status cache
        clearstatcache(true, $path);

        // File Exists and is a Directory
        // Since everything in Unix is a file, including directories. So we should check both.
        return (file_exists($path) === true) && (is_dir($path) === true);
    }

    private function echo(string $pattern, ...$params)
    {
        if ($this->options['silence']) {
            return;
        }

        echo sprintf($pattern, ...$params);
    }

    private function baseName($file)
    {
        return pathinfo($file, PATHINFO_BASENAME);
    }
}
