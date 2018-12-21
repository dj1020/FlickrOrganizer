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
    private $isMove            = false;

    public function execute()
    {
        $this->initAlbums();
        $this->initMediaFiles($this->config->mediaDir);

        // Todo-Ken: mock/debug only
//        dd(
//            count($this->mediaFiles),
//            array_sum(array_pluck($this->albums, 'photo_count'))
//        );

        foreach ($this->mediaFiles as $file) {
            $albumNames = $this->getMediaAlbumNames($file) ?: ['Flickr_Others'];
            if ($this->isMove) {
                $this->moveMediaToAlbumFolder($file, $albumNames);
            } else {
                $this->copyMediaToAlbumFolder($file, $albumNames);
            }
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
        $filename = pathinfo($file, PATHINFO_BASENAME);
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
            return $this->decodeTitle($this->albums[$albumId]->title);
        }, $albumIds);
    }

    private function decodeTitle($title)
    {
        return $title;
    }

    private function moveMediaToAlbumFolder($file, array $albumNames)
    {
    }

    private function copyMediaToAlbumFolder($file, array $albumNames)
    {
        foreach ($albumNames as $albumName) {
            $folderPath = $this->createAlbumFolder($albumName);

            die();
            // Todo-Ken: TBC
            // $success = copy($file, $folderPath);

            // Todo-Ken: Do some success/error log here

        }

        // Todo-Ken: Do some error log here
    }

    /**
     * @param bool $isMove
     * @return Organizer
     */
    public function setIsMove(bool $isMove)
    {
        $this->isMove = $isMove;

        return $this;
    }

    private function createAlbumFolder($albumName)
    {
        $folderPath = rtrim($this->config->outputDir, '/') . '/' . $albumName;

        if ($this->isValidDirectory($folderPath)) {
            return $folderPath;
        }

        $success = mkdir($folderPath, 0777, true);
        if ( ! $success) {
            throw new \RuntimeException(sprintf('Failed to create folder %s', $folderPath));
        };

        return $folderPath;
    }

    /*
     * ref: https://www.codezuzu.com/2015/03/how-to-check-if-directory-exists-in-php/
     */
    public function isValidDirectory($path)
    {
        // Clears file status cache
        clearstatcache(true, $path);

        // File Exists and is a Directory
        // Since everything in Unix is a file, including directories. So we should check both.
        if ((file_exists($path) === true) && (is_dir($path) === true)) {
            return true;
        }

        return false;
    }
}
