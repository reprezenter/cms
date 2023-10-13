<?php

require_once(PUBLIC_PATH . '/vendor/PHPImageWorkshop/ImageWorkshop.php');

class Image {

    const DIRECTORY_SEPARATOR = '/';
    const TYPE_CROP = 'crop';
    const TYPE_RESIZE = 'resize';
    const TYPE_RESIZE_AND_CROP = 'resizeAndCrop';
    const TYPE_RESIZE_TO_FIT = 'resizeToFit';
    const TYPE_CUSTOM_CROP = 'customCrop';
    const TYPE_RESIZE_TO_FIT_WITH_BLUR_BG = 'resizeWithBlur';
    const EXT_PNG = 'png';
    const EXT_JPG = 'jpg';
    const DEFAULT_EXT = self::EXT_PNG;
    const DEFAULT_QUALITY = 95;
    const DEFAULT_BACKGROUND_COLOR = null;
    const DEFAULT_WATERMARK_OPACITY = 50;
    const DEFAULT_WATERMARK_PROPORTION = 0.25;
    const IMAGE_PUBLIC_PATH = true;
    const ENTITY_FOLDER = 'entity';
    const ENTITY_ID_BLOG = 1;
    const TMP_SESSiON_PREFIX = 'tmp';
    const ENTITY_1_IMAGE_WIDTH = 400;

    /**
     * Sciezka do pliku zrodlowego, relatywna w sostunku do $rootDirectoryAbsolutePath
     * @var String
     */
    private $srcFileRelativePath;

    /**
     * Sciezka relatywna(dla przegladarki, PUBLIC_PATH) obrazu wyjsciowego(utworzonego thumbsa)
     * @var String
     */
    private $destFileRelativePath;

    /**
     * nazwa pliku wyjsciowego
     * @var String 
     */
    private $destFileName;

    /**
     * sciezka absolutna do katalogu glownego
     * @var String
     */
    private $rootDirectoryAbsolutePath = PUBLIC_PATH . DIRECTORY_SEPARATOR;

    /**
     * sciezka absolutna do katalogu glownego dla publicznych obrazów
     * @var String
     */
    private $rootDirectoryAbsolutePublicPath = PUBLIC_PATH;

    /**
     * nazwa katalogu na thumbsy(tam je zapisujemy), jest tworzony w katalogu $rootDirectoryAbsolutePath
     * @var String 
     */
    private $imagesDirectoryName = 'images';

    /**
     *
     * @var Array
     */
    private $options;

    /**
     *
     * @var String
     */
    private $defaultFilePath;

    public function __construct($srcRelativeFilePath = null, Array $options = array()) {
        if ($srcRelativeFilePath) {
            $this->setSrcFileRelativePath($srcRelativeFilePath);
        }

        if ($options) {
            $this->setOptions($options);
        }
    }

    public function getSrcFileExt() {
        $fileInfo = pathinfo($this->getSrcFileAbsolutePath());
        return $fileInfo['extension'];
    }

    public function getSrcFileName() {
        $fileInfo = pathinfo($this->getSrcFileAbsolutePath());
        return $fileInfo['filename'];
    }

    public function getSrcFileDirectoryAbsolutePath() {
        $fileInfo = pathinfo($this->getSrcFileAbsolutePath());
        return $fileInfo['dirname'];
    }

    public function getSrcFileAbsolutePath() {
        return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $this->getRootDirectoryAbsolutePath() . self::DIRECTORY_SEPARATOR . ltrim($this->srcFileRelativePath, '/\\'));
    }

    public function getSrcSize() {
        return filesize($this->getSrcFileAbsolutePath());
    }

    public function getSrcFileRelativePath() {
        return $this->srcFileRelativePath;
    }

    public function setSrcFileRelativePath($srcFileRelativePath) {
        //jesli podano sciezke absolutna, to sprobuj ja obciac
        if (file_exists($srcFileRelativePath)) {
            $srcFileRelativePath = str_replace($this->getRootDirectoryAbsolutePath(), '', $srcFileRelativePath);
        }

        $srcFileRelativePath = self::DIRECTORY_SEPARATOR . trim($srcFileRelativePath, '/\\');

        $this->srcFileRelativePath = $srcFileRelativePath;
        return $this;
    }

    public function getRootDirectoryAbsolutePath() {
        if (self::IMAGE_PUBLIC_PATH) {
            return $this->rootDirectoryAbsolutePath;
        } else {
            return $this->rootDirectoryAbsolutePublicPath;
        }
    }

    public function setRootDirectoryAbsolutePath($rootDirectoryAbsolutePath) {
        $this->rootDirectoryAbsolutePath = $rootDirectoryAbsolutePath;
        return $this;
    }

    public function getImagesDirectoryAbsolutePath() {
        return rtrim($this->getSrcFileDirectoryAbsolutePath(), DIRECTORY_SEPARATOR) . self::DIRECTORY_SEPARATOR . $this->imagesDirectoryName;
    }

    public function getImagesDirectoryName() {
        return $this->imagesDirectoryName;
    }

    public function setImagesDirectoryName($imagesDirectoryName) {
        if (!$imagesDirectoryName) {
            throw new \InvalidArgumentException();
        }

        $this->imagesDirectoryName = $imagesDirectoryName;
        return $this;
    }

    public function getDestFileRelativePath() {
        if (!$this->destFileRelativePath) {
            $imagePath = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, ltrim($this->srcFileRelativePath, DIRECTORY_SEPARATOR));
            $imagePathExploded = explode(DIRECTORY_SEPARATOR, $imagePath);
            array_pop($imagePathExploded);
            
            try {
                $image = $this->createImageOnDemand();
                if (self::IMAGE_PUBLIC_PATH) {
                    $this->destFileRelativePath = implode('/', $imagePathExploded) . '/images/' . $image;
                } else {
                    $this->destFileRelativePath = implode('/', $imagePathExploded) . '/images/' . $image;
                }
            } catch (\Exception $exc) {
                $mime = self::getMimeType($imagePath);
                $this->destFileRelativePath = '/sibelius/file/image/file/.' . implode('.', str_replace(array('/', '\\'), '.', $imagePathExploded)) . basename($imagePath) . '/contentType/' . str_replace('/', '.', $mime);
            }


            //$contentType = $this->options['ext'];
            //if ($contentType == 'jpg') {
            //   $contentType = 'jpeg';
            //}
            //$this->destFileRelativePath = '/sibelius/file/image/file/_' . implode('_', str_replace(array('/', '\\'), '_', $imagePathExploded)) . 'images_' . $image . '/contentType/image_' . $contentType;
        }

        return $this->destFileRelativePath;
    }

    public function setDestFileRelativePath($destFileRelativePath) {
        $this->destFileRelativePath = $destFileRelativePath;
        return $this;
    }

    public function getDestFileAbsolutePath() {
        if (!$this->destFileRelativePath) {
            $this->getDestFileRelativePath();
        }

        return $this->getImagesDirectoryAbsolutePath() . self::DIRECTORY_SEPARATOR . $this->destFileName;
    }

    public function setDestFileAbsolutePath($destFileAbsolutePath) {
        throw new \Exception();
        return $this;
    }

    public function getDefaultFilePath() {
        return $this->defaultFilePath;
    }

    public function setDefaultFilePath($defaultFilePath) {
        $this->defaultFilePath = $defaultFilePath;
        return $this;
    }

    public function getOptions() {
        return $this->options;
    }

    public function setOptions($options) {
        if (count($options) === 1) {
            $keys = array_keys($options);
            $type = $keys[0];
            $options = $options[$type];
            $options['type'] = $type;
        }

        if (!isset($options['type'])) {
            throw new \Exception('No type provided');
        }

        if (
                $options['type'] != self::TYPE_CROP &&
                $options['type'] != self::TYPE_RESIZE &&
                $options['type'] != self::TYPE_RESIZE_AND_CROP &&
                $options['type'] != self::TYPE_RESIZE_TO_FIT &&
                $options['type'] != self::TYPE_CUSTOM_CROP &&
                $options['type'] != self::TYPE_RESIZE_TO_FIT_WITH_BLUR_BG
        ) {
            throw new \Exception('Wrong type provided: ' . $options['type']);
        }

        if (!isset($options['ext'])) {
            $options['ext'] = self::DEFAULT_EXT;
        }
        if (
                $options['ext'] != self::EXT_JPG &&
                $options['ext'] != self::EXT_PNG
        ) {
            throw new \Exception('Wrong ext provided: ' . $options['ext']);
        }

        if (!isset($options['quality'])) {
            $options['quality'] = self::DEFAULT_QUALITY;
        }

        if (!isset($options['background_color'])) {
            $options['background_color'] = self::DEFAULT_BACKGROUND_COLOR;
        }

        if (!isset($options['watermark'])) {
            $options['watermark'] = null;
        }

        $this->options = $options;

        return $this;
    }

    public static function getAvailableOption() {
        return array(
            'type' => array(self::TYPE_CROP, self::TYPE_RESIZE, self::TYPE_RESIZE_AND_CROP, self::TYPE_RESIZE_TO_FIT),
            'ext' => array(self::EXT_PNG, self::EXT_JPG),
        );
    }

    public static function getDeafultAvailableOption($key) {
        $array = array(
            'type' => self::TYPE_RESIZE_TO_FIT,
            'ext' => self::EXT_PNG,
        );
        if ($key) {
            return $array[$key];
        }
        return $array;
    }

    public function createImageOnDemand() {

        $srcFileAbsolutePath = $this->getSrcFileAbsolutePath();
        $imagesDirectoryAbsolutePath = $this->getImagesDirectoryAbsolutePath();
        $this->destFileName = md5($this->srcFileRelativePath . json_encode($this->options)) . '.' . $this->options['ext'];
        $destFileAbsolutePath = $imagesDirectoryAbsolutePath . self::DIRECTORY_SEPARATOR . $this->destFileName;
        //image already exists return its name
        if (file_exists($destFileAbsolutePath)) {
            return $this->destFileName;
        } else {
            //if original image exists
            if (file_exists($srcFileAbsolutePath) && is_file($srcFileAbsolutePath)) {
                
                call_user_func_array(
                        array($this, $this->options['type']), array($srcFileAbsolutePath, $imagesDirectoryAbsolutePath, $this->destFileName, $this->options)
                );

                if (file_exists($destFileAbsolutePath)) {
                    return $this->destFileName;
                }
            }
        }

        return false;
    }

    private function resize($srcFileAbsolutePath, $imagesDirectoryAbsolutePath, $destFileName, $options) {
        $layer = \PHPImageWorkshop\ImageWorkshop::initFromPath($srcFileAbsolutePath);

        //set defaults
        if (!key_exists('width', $options)) {
            $options['width'] = null;
        }
        if (!key_exists('height', $options)) {
            $options['height'] = null;
        }
        if (!key_exists('proportion', $options)) {
            $options['proportion'] = true;
        }
        //resizeinPixel
        $layer->resizeInPixel($options['width'], $options['height'], $options['proportion']);

        if ($options['watermark']) {
            $this->addWatermark($layer, $options['watermark']);
        }

        return $layer->save($imagesDirectoryAbsolutePath, $destFileName, true, $options['background_color'], $options['quality']);
    }

    private function crop($srcFileAbsolutePath, $imagesDirectoryAbsolutePath, $destFileName, $options) {
        $layer = \PHPImageWorkshop\ImageWorkshop::initFromPath($srcFileAbsolutePath);

        //set defaults
        if (!key_exists('width', $options)) {
            $options['width'] = null;
        }
        if (!key_exists('height', $options)) {
            $options['height'] = null;
        }
        //crop
        $layer->cropInPixel($options['width'], $options['height'], 0, 0, 'LT');

        return $layer->save($imagesDirectoryAbsolutePath, $destFileName, true, $options['background_color'], $options['quality']);
    }

    private function resizeAndCrop($srcFileAbsolutePath, $imagesDirectoryAbsolutePath, $destFileName, $options) {
        $layer = \PHPImageWorkshop\ImageWorkshop::initFromPath($srcFileAbsolutePath);

        //set defaults
        if (!key_exists('width', $options)) {
            throw new \InvalidArgumentException;
        }
        if (!key_exists('height', $options)) {
            throw new \InvalidArgumentException;
        }

        if (key_exists('image_width', $options) || key_exists('image_height', $options)) {
            $newWidth = $options['image_width'];
            if (key_exists('image_height', $options)) {
                $newHeight = $options['image_height'];
            }
        } else {
            //wyliczenie wartosci optymalnych, tak zeby obraz byl jak najwiekszy
            $widthProp = $layer->getWidth() / $options['width'];
            $heightProp = $layer->getHeight() / $options['height'];

            if ($heightProp < $widthProp) {
                $newHeight = $options['height'];
                //$newWidth = $layer->getWidth() / $heightProp;
                $newWidth = null;
            } else {
                $newWidth = $options['width'];
                //$newHeight = $layer->getHeight() / $widthProp;
                $newHeight = null;
            }
        }

        //resize in pixel
        $layer->resizeInPixel($newWidth, $newHeight, true);

        if (key_exists('x', $options) && key_exists('y', $options)) {
            $positionX = $options['x'];
            $positionY = $options['y'];
        } else {
            //pozycja ciecia wyznaczana automatycznie, optymalnie na srodku
            if ($newWidth == null) {
                //crop horizontal
                $positionY = 0;
                $positionX = ( $layer->getWidth() - $options['width'] ) / 2;
            } else {
                //crop vertical
                $positionX = 0;
                $positionY = ( $layer->getHeight() - $options['height'] ) / 2;
            }
        }

        //crop in pixel
        $position = 'LT'; //left Top
        $layer->cropInPixel($options['width'], $options['height'], $positionX, $positionY, $position);

        if ($options['watermark']) {
            $this->addWatermark($layer, $options['watermark']);
        }

        return $layer->save($imagesDirectoryAbsolutePath, $destFileName, true, $options['background_color'], $options['quality']);
    }

    private function customCrop($srcFileAbsolutePath, $imagesDirectoryAbsolutePath, $destFileName, $options) {
        $layer = \PHPImageWorkshop\ImageWorkshop::initFromPath($srcFileAbsolutePath);

        //set defaults
        if (!key_exists('width', $options)) {
            throw new \InvalidArgumentException;
        }
        if (!key_exists('height', $options)) {
            throw new \InvalidArgumentException;
        }
        if (!key_exists('left', $options)) {
            throw new \InvalidArgumentException;
        }
        if (!key_exists('top', $options)) {
            throw new \InvalidArgumentException;
        }
        if (!key_exists('zoom', $options)) {
            throw new \InvalidArgumentException;
        }


        //wyliczenie wartosci optymalnych, tak zeby obraz byl jak najwiekszy
        $newWidth = $layer->getWidth() * $options['zoom'];
        $newHeight = $layer->getHeight() * $options['zoom'];

        //resize in pixel
        $layer->resizeInPixel($newWidth, $newHeight, true);

        //crop in pixel
        $position = 'LT'; //left Top
        $layer->cropInPixel($options['width'], $options['height'], $options['left'], $options['top'], $position);

        if ($options['watermark']) {
            $this->addWatermark($layer, $options['watermark']);
        }

        return $layer->save($imagesDirectoryAbsolutePath, $destFileName, true, $options['background_color'], $options['quality']);
    }

    private function resizeToFit($srcFileAbsolutePath, $imagesDirectoryAbsolutePath, $destFileName, $options) {
        if (!isset($options['width'])) {
            throw new \Exception('No width set');
        }

        if (!isset($options['height'])) {
            throw new \Exception('No height set');
        }

        $layer = \PHPImageWorkshop\ImageWorkshop::initFromPath($srcFileAbsolutePath);
        $srcFileWidth = $layer->getWidth();
        $srcFileHeight = $layer->getHeight();

        //wylicz nowe wartosci szerokosci obrazu, bo klasa ta po przeskalowaniu ich nie zwraca, tylko
        //wypelnia puste miejsce tlem
        $newWidth = $srcFileWidth;
        $newHeight = $srcFileHeight;

        //jesli szerokosc sie nie miesci w granicy, to ja zmniejsz, a wysokosc wylicz na podstawie proprcji
        if ($options['width'] < $newWidth) {
            $newHeight = round($options['width'] / $newWidth * $newHeight);
            $newWidth = $options['width'];
        }

        //jesli wysokosc sie nie miesci w granicy, to ja zmniejsz, a szerokosc wylicz na podstawie proprcji
        //po tym na pewno obraz miesci sie w wybranej ramie
        if ($options['height'] < $newHeight) {
            $newWidth = round($options['height'] / $newHeight * $newWidth);
            $newHeight = $options['height'];
        }

        //skaluj tylko jesli jest taka potrzeba bo obraz oryginalu moze sie miescic
        //skalowanie bez utrzymania proporcji, bo wyliczone przeze mnie wartosci maja zachowane proporcje
        if ($newWidth != $srcFileWidth || $newHeight != $srcFileHeight) {
            $layer->resizeInPixel($newWidth, $newHeight, false, 0, 0, 'LT');
        }

        if ($options['watermark']) {
            $this->addWatermark($layer, $options['watermark']);
        }

        return $layer->save($imagesDirectoryAbsolutePath, $destFileName, true, $options['background_color'], $options['quality']);
    }

    private function resizeWithBlur($srcFileAbsolutePath, $imagesDirectoryAbsolutePath, $destFileName, $options) {
        if (!isset($options['width'])) {
            throw new \Exception('No width set');
        }

        if (!isset($options['height'])) {
            throw new \Exception('No height set');
        }

        $layer = \PHPImageWorkshop\ImageWorkshop::initFromPath($srcFileAbsolutePath);
        $srcFileWidth = $layer->getWidth();
        $srcFileHeight = $layer->getHeight();

        $position = 'LT'; //left Top
        $layer->cropInPixel($srcFileWidth / 3, $srcFileHeight / 3, $srcFileWidth / 3, $srcFileHeight / 3, $position);

        $newWidth = $options['width'];
        $newHeight = $options['height'];

        if ($newWidth != $srcFileWidth || $newHeight != $srcFileHeight) {
            $layer->resizeInPixel($newWidth, $newHeight, false, 0, 0, 'LT');
        }
        for ($x = 1; $x <= 50; $x++) {
            $layer->applyFilter(IMG_FILTER_GAUSSIAN_BLUR);
        }
        $origin = \PHPImageWorkshop\ImageWorkshop::initFromPath($srcFileAbsolutePath);

        $newWidth = $srcFileWidth;
        $newHeight = $srcFileHeight;

        //jesli szerokosc sie nie miesci w granicy, to ja zmniejsz, a wysokosc wylicz na podstawie proprcji
        if ($options['width'] < $newWidth) {
            $newHeight = round($options['width'] / $newWidth * $newHeight);
            $newWidth = $options['width'];
        }

        //jesli wysokosc sie nie miesci w granicy, to ja zmniejsz, a szerokosc wylicz na podstawie proprcji
        //po tym na pewno obraz miesci sie w wybranej ramie
        if ($options['height'] < $newHeight) {
            $newWidth = round($options['height'] / $newHeight * $newWidth);
            $newHeight = $options['height'];
        }

        //skaluj tylko jesli jest taka potrzeba bo obraz oryginalu moze sie miescic
        //skalowanie bez utrzymania proporcji, bo wyliczone przeze mnie wartosci maja zachowane proporcje
        if ($newWidth != $srcFileWidth || $newHeight != $srcFileHeight) {
            $origin->resizeInPixel($newWidth, $newHeight, false, 0, 0, 'LT');
        }

        $layer->addLayerOnTop($origin, $options['width'] / 2 - $origin->getWidth() / 2, $options['height'] / 2 - $origin->getHeight() / 2, 'LT');
        $layer->mergeAll();

        if ($options['watermark']) {
            $this->addWatermark($layer, $options['watermark']);
        }

        return $layer->save($imagesDirectoryAbsolutePath, $destFileName, true, $options['background_color'], $options['quality']);
    }

    private function addWatermark($layer, $watermarkAbsolutePath) {
        /* @var $layer \PHPImageWorkshop\Core\ImageWorkshopLayer */
        $watermark = \PHPImageWorkshop\ImageWorkshop::initFromPath($watermarkAbsolutePath);

        $orginalWidth = $layer->getWidth();
        $watermarkWidth = $watermark->getWidth();

        $newWatermarkWidth = round(self::DEFAULT_WATERMARK_PROPORTION * $orginalWidth);
        if ($watermarkWidth > $newWatermarkWidth) {
            $watermark->resizeInPixel($newWatermarkWidth, null, true);
        }

        $watermark->opacity(self::DEFAULT_WATERMARK_OPACITY);

        $layer->addLayerOnTop($watermark, 0, 0, 'RT');
        $layer->mergeAll();
    }

    //zamiast set srcFileRelativePath jeżeli plik jest tylko jeden w katalogu
    public function scanFormSingleFile($_relativePath) {
        $relativePath = trim(trim($_relativePath, '/'), '\\');
        $this->setSrcFileRelativePath($relativePath);
        $fileName = false;
        $dir = $this->rootDirectoryAbsolutePath . $relativePath;
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if (!is_dir($dir . DIRECTORY_SEPARATOR . $file)) {
                    $fileName = $file;
                }
            }
            if ($fileName) {

                $this->srcFileRelativePath = $relativePath . DIRECTORY_SEPARATOR . $fileName;
            }
        }
        if (!$fileName) {
            $this->srcFileRelativePath = $this->defaultFilePath;
        }
        return $this;
    }

    public static function clearFolderContent($path) {
        if (!($path) || !is_dir($path)) {
            throw new \InvalidArgumentException();
        }
        $files = glob(rtrim($path, "/") . '/*');
        $result = true;
        foreach ($files as $file) {
            if (is_file($file))
                $result = $result && unlink($file);
        }
        return $result;
    }

    public static function safeFileName($name) {
        $array = explode('.', $name);
        $extension = array_pop($array);
        return preg_replace('/[^a-z0-9]+/', '-', strtolower(implode('', $array))) . '.' . $extension;
    }

    public static function mkdir_r($dirName, $rights = 0777) {
        $dirs = explode(DIRECTORY_SEPARATOR, $dirName);
        $dir = '';
        foreach ($dirs as $part) {
            $dir .= $part . DIRECTORY_SEPARATOR;
            if (!is_dir($dir) && strlen($dir) > 0)
                if (!mkdir(rtrim($dir, DIRECTORY_SEPARATOR), $rights)) {
                    $error = error_get_last();
                    echo $error['message'];
                };
        }
    }

    public static function getMimeType($path) {
        $mimeTypes = array(
            "323" => "text/h323",
            "acx" => "application/internet-property-stream",
            "ai" => "application/postscript",
            "aif" => "audio/x-aiff",
            "aifc" => "audio/x-aiff",
            "aiff" => "audio/x-aiff",
            "asf" => "video/x-ms-asf",
            "asr" => "video/x-ms-asf",
            "asx" => "video/x-ms-asf",
            "au" => "audio/basic",
            "avi" => "video/x-msvideo",
            "axs" => "application/olescript",
            "bas" => "text/plain",
            "bcpio" => "application/x-bcpio",
            "bin" => "application/octet-stream",
            "bmp" => "image/bmp",
            "c" => "text/plain",
            "cat" => "application/vnd.ms-pkiseccat",
            "cdf" => "application/x-cdf",
            "cer" => "application/x-x509-ca-cert",
            "class" => "application/octet-stream",
            "clp" => "application/x-msclip",
            "cmx" => "image/x-cmx",
            "cod" => "image/cis-cod",
            "cpio" => "application/x-cpio",
            "crd" => "application/x-mscardfile",
            "crl" => "application/pkix-crl",
            "crt" => "application/x-x509-ca-cert",
            "csh" => "application/x-csh",
            "css" => "text/css",
            "dcr" => "application/x-director",
            "der" => "application/x-x509-ca-cert",
            "dir" => "application/x-director",
            "dll" => "application/x-msdownload",
            "dms" => "application/octet-stream",
            "doc" => "application/msword",
            "dot" => "application/msword",
            "dvi" => "application/x-dvi",
            "dxr" => "application/x-director",
            "eps" => "application/postscript",
            "etx" => "text/x-setext",
            "evy" => "application/envoy",
            "exe" => "application/octet-stream",
            "fif" => "application/fractals",
            "flr" => "x-world/x-vrml",
            "gif" => "image/gif",
            "gtar" => "application/x-gtar",
            "gz" => "application/x-gzip",
            "h" => "text/plain",
            "hdf" => "application/x-hdf",
            "hlp" => "application/winhlp",
            "hqx" => "application/mac-binhex40",
            "hta" => "application/hta",
            "htc" => "text/x-component",
            "htm" => "text/html",
            "html" => "text/html",
            "htt" => "text/webviewhtml",
            "ico" => "image/x-icon",
            "ief" => "image/ief",
            "iii" => "application/x-iphone",
            "ins" => "application/x-internet-signup",
            "isp" => "application/x-internet-signup",
            "jfif" => "image/pipeg",
            "jpe" => "image/jpeg",
            "jpeg" => "image/jpeg",
            "jpg" => "image/jpeg",
            "js" => "application/x-javascript",
            "latex" => "application/x-latex",
            "lha" => "application/octet-stream",
            "lsf" => "video/x-la-asf",
            "lsx" => "video/x-la-asf",
            "lzh" => "application/octet-stream",
            "m13" => "application/x-msmediaview",
            "m14" => "application/x-msmediaview",
            "m3u" => "audio/x-mpegurl",
            "man" => "application/x-troff-man",
            "mdb" => "application/x-msaccess",
            "me" => "application/x-troff-me",
            "mht" => "message/rfc822",
            "mhtml" => "message/rfc822",
            "mid" => "audio/mid",
            "mny" => "application/x-msmoney",
            "mov" => "video/quicktime",
            "movie" => "video/x-sgi-movie",
            "mp2" => "video/mpeg",
            "mp3" => "audio/mpeg",
            "mpa" => "video/mpeg",
            "mpe" => "video/mpeg",
            "mpeg" => "video/mpeg",
            "mpg" => "video/mpeg",
            "mpp" => "application/vnd.ms-project",
            "mpv2" => "video/mpeg",
            "ms" => "application/x-troff-ms",
            "mvb" => "application/x-msmediaview",
            "nws" => "message/rfc822",
            "oda" => "application/oda",
            "p10" => "application/pkcs10",
            "p12" => "application/x-pkcs12",
            "p7b" => "application/x-pkcs7-certificates",
            "p7c" => "application/x-pkcs7-mime",
            "p7m" => "application/x-pkcs7-mime",
            "p7r" => "application/x-pkcs7-certreqresp",
            "p7s" => "application/x-pkcs7-signature",
            "pbm" => "image/x-portable-bitmap",
            "pdf" => "application/pdf",
            "pfx" => "application/x-pkcs12",
            "pgm" => "image/x-portable-graymap",
            "pko" => "application/ynd.ms-pkipko",
            "pma" => "application/x-perfmon",
            "pmc" => "application/x-perfmon",
            "pml" => "application/x-perfmon",
            "pmr" => "application/x-perfmon",
            "pmw" => "application/x-perfmon",
            "pnm" => "image/x-portable-anymap",
            "pot" => "application/vnd.ms-powerpoint",
            "ppm" => "image/x-portable-pixmap",
            "pps" => "application/vnd.ms-powerpoint",
            "ppt" => "application/vnd.ms-powerpoint",
            "prf" => "application/pics-rules",
            "ps" => "application/postscript",
            "pub" => "application/x-mspublisher",
            "qt" => "video/quicktime",
            "ra" => "audio/x-pn-realaudio",
            "ram" => "audio/x-pn-realaudio",
            "ras" => "image/x-cmu-raster",
            "rgb" => "image/x-rgb",
            "rmi" => "audio/mid",
            "roff" => "application/x-troff",
            "rtf" => "application/rtf",
            "rtx" => "text/richtext",
            "scd" => "application/x-msschedule",
            "sct" => "text/scriptlet",
            "setpay" => "application/set-payment-initiation",
            "setreg" => "application/set-registration-initiation",
            "sh" => "application/x-sh",
            "shar" => "application/x-shar",
            "sit" => "application/x-stuffit",
            "snd" => "audio/basic",
            "spc" => "application/x-pkcs7-certificates",
            "spl" => "application/futuresplash",
            "src" => "application/x-wais-source",
            "sst" => "application/vnd.ms-pkicertstore",
            "stl" => "application/vnd.ms-pkistl",
            "stm" => "text/html",
            "svg" => "image/svg+xml",
            "sv4cpio" => "application/x-sv4cpio",
            "sv4crc" => "application/x-sv4crc",
            "t" => "application/x-troff",
            "tar" => "application/x-tar",
            "tcl" => "application/x-tcl",
            "tex" => "application/x-tex",
            "texi" => "application/x-texinfo",
            "texinfo" => "application/x-texinfo",
            "tgz" => "application/x-compressed",
            "tif" => "image/tiff",
            "tiff" => "image/tiff",
            "tr" => "application/x-troff",
            "trm" => "application/x-msterminal",
            "tsv" => "text/tab-separated-values",
            "txt" => "text/plain",
            "uls" => "text/iuls",
            "ustar" => "application/x-ustar",
            "vcf" => "text/x-vcard",
            "vrml" => "x-world/x-vrml",
            "wav" => "audio/x-wav",
            "wcm" => "application/vnd.ms-works",
            "wdb" => "application/vnd.ms-works",
            "wks" => "application/vnd.ms-works",
            "wmf" => "application/x-msmetafile",
            "wps" => "application/vnd.ms-works",
            "wri" => "application/x-mswrite",
            "wrl" => "x-world/x-vrml",
            "wrz" => "x-world/x-vrml",
            "xaf" => "x-world/x-vrml",
            "xbm" => "image/x-xbitmap",
            "xla" => "application/vnd.ms-excel",
            "xlc" => "application/vnd.ms-excel",
            "xlm" => "application/vnd.ms-excel",
            "xls" => "application/vnd.ms-excel",
            "xlsx" => "vnd.ms-excel",
            "xlt" => "application/vnd.ms-excel",
            "xlw" => "application/vnd.ms-excel",
            "xof" => "x-world/x-vrml",
            "xpm" => "image/x-xpixmap",
            "xwd" => "image/x-xwindowdump",
            "z" => "application/x-compress",
            "zip" => "application/zip",
            "png" => "image/png"
        );
        $info = pathinfo($path);
        $ext = strtolower($info['extension']);
        return $mimeTypes[$ext];
    }

    public function __toString() {
        return $this->getDestFileRelativePath();
    }

}
