<?php

namespace Src\Models;


use Slim\Http\UploadedFile;


class Image
{
    const ALLOWED_MIME_TYPE_ARR = ['image/jpeg', 'image/jpg'];

    const MAX_IMAGE_SIZE = 5;//in Mega Byte or above MB

    const MAX_NUMBER_OF_UPLOADED_IMAGES = 3;// number of uploaded

    const IMAGE_SIZE_UNITS_ARR      = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    const IMAGE_SIZE_LARGE_UNIT_ARR = ['MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

    /**
     * DESC
     *
     * @param array $images
     * @param array $userInfoArr
     *
     * @return array
     *
     * @author Ibraheem Abu Kaff
     */
    public function uploadAndDetect(array $images): array
    {
        if (empty(array_filter($images))) {
            $error = ['status' => 400, 'error' => 'empty images'];

            return $error;
        }

        $listOfImages = $images[ApiConstants::FILES] ?? [];

        if (count($listOfImages) > static::MAX_NUMBER_OF_UPLOADED_IMAGES) {
            //todo: throw exception
            $error = ['status' => 400, 'error' => 'exceeded the maximum number of allowed images to upload'];

            return $error;

        }

        if (empty(array_filter($listOfImages))) {
            $error = ['status' => 400, 'error' => 'empty images'];

            return $error;
        }

        $data = [];
        //loop through each file
        //check the type, size, and try to compress it
        /** @var UploadedFile $image */
        foreach ($listOfImages as $key => $image) {
            //check the image instance

            $imageMimeType = strtolower($image->getClientMediaType());
            $sizeArr       = $this->formatBytes($image->getSize());
            $sizeUnit      = strtoupper($sizeArr[ApiConstants::UNIT] ??'');
            $sizeInNumber  = $sizeArr[ApiConstants::SIZE_AS_NUMBER] ?? 0;

            //check the image type
            if (!$this->isValidMimeType($imageMimeType)) {
                $error = ['status' => 400, 'error' => 'invalid mime type, jpeg|jpg is the only accepted image type', 'img' => $image->getClientFilename()];

                return $error;
            }
            //check the image size
            if (!$this->isValidSize($sizeUnit, $sizeInNumber)) {
                $error = ['status' => 400, 'error' => 'invalid image size', 'img' => $image->getClientFilename()];

                return $error;
            }

            //store the image locally
            $fullPath = $this->saveImageLocally($image);

            $command = getenv('command');
            $command = sprintf($command, $fullPath);

            $predication = shell_exec($command . " 2>&1");

            preg_match_all('/(SFW|NSFW)\s*score\s*:\s*(.*)/i', $predication, $outputMatch);


            if (!empty($outputMatch[0])) {
                $sfWOrNsfW1 = ($outputMatch[0][0]??'');
                $sfWOrNsfW2 = ($outputMatch[0][1]??'');

                $result1 = static::extractSfwAndNsfw($sfWOrNsfW1);
                $result2 = static::extractSfwAndNsfw($sfWOrNsfW2);

                $data[] = [
                    'img'    => $image->getClientFilename(),
                    'result' => [$result1, $result2],
                ];

            }

            unlink($fullPath);
        };

        return ['status' => 200, 'data' => $data];

    }

    public static function extractSfwAndNsfw($dataAsString)
    {
        $explodedSfwOrNswf = explode('score:', $dataAsString);

        if (strtoupper(trim($explodedSfwOrNswf[0])) === 'SFW') {
            return ['type' => 'sfw', 'label' => 'Safe for work', 'score' => floatval(trim($explodedSfwOrNswf[1]))];
        } elseif (strtoupper(trim($explodedSfwOrNswf[0])) === 'NSFW') {
            return ['type' => 'nsfw', 'label' => 'Not safe for work', 'score' => floatval(trim($explodedSfwOrNswf[1]))];
        }

        return ['type' => '', 'label' => '', 'score' => 0];
    }

    /**
     * DESC
     *
     * @param UploadedFile $image
     *
     * @return string
     *
     * @author Ibraheem Abu Kaff
     */
    public function saveImageLocally($image)
    {
        $imageNameArr =
            ['time'      => time(),
             'name'      => $image->getClientFilename(),
             'extension' => $image->getClientMediaType(),
             'uuid'      => uniqid(),
            ];

        $dirPath = __DIR__ . "/../../imgs/";
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755);
        }

        $imageName = md5(json_encode($imageNameArr)) . '.' . explode('/', $image->getClientMediaType())[1];     //stored on the server
        $path      = $dirPath . $imageName;
        $image->moveTo($path);

        return $path;
    }


    /**
     * DESC
     *
     * @param $imageMimeType
     *
     * @return bool
     *
     * @author Ibraheem Abu Kaff
     */
    public function isValidMimeType($imageMimeType)
    {
        //check the image type
        if (!in_array($imageMimeType, static::ALLOWED_MIME_TYPE_ARR)) {
            //todo: throw exception
            return false;
        }

        return true;
    }

    /**
     * DESC
     *
     * @param $sizeUnit
     * @param $sizeInNumber
     *
     * @return bool
     *
     * @author Ibraheem Abu Kaff
     */
    public function isValidSize($sizeUnit, $sizeInNumber)
    {
        if (in_array($sizeUnit, static::IMAGE_SIZE_LARGE_UNIT_ARR) && $sizeInNumber > static::MAX_IMAGE_SIZE) {
            //todo: throw exception
            return false;
        }

        return true;
    }

    /**
     * formatBytes
     *
     * @param     $size
     * @param int $precision
     *
     * @return array
     *
     * @author Ibraheem Abu Kaff
     */
    public function formatBytes($size, $precision = 2)
    {
        $units = static::IMAGE_SIZE_UNITS_ARR;
        $step  = 1024;
        $i     = 0;
        while (($size / $step) > 0.9) {
            $size = $size / $step;
            $i++;
        }

        return ['sizeAsNumber' => round($size, $precision), 'unit' => $units[$i]];
    }
}