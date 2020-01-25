<?php
namespace App\Domain\Message\Service;

class WeWorkSenderHelper {

    /**
     * Moves the uploaded file to the upload directory and assigns it a unique name
     * to avoid overwriting an existing uploaded file.
     *
     * @param string $directory directory to which the file is moved
     * @param UploadedFile $uploaded file uploaded file to move
     * @return string filename of moved file
     */
    static public function moveUploadedFile(UploadedFile $uploadedFile, $weWork, &$mediaFiles)
    {

        $pathParts = pathinfo($uploadedFile->getClientFilename());
        //$basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
        $md5 = md5_file($uploadedFile->file);
        $filename = sprintf('%s.%0.8s', $pathParts['filename'] . '_' . substr($md5, 0, 10), $pathParts['extension']);

        $tmpDir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
        $targetPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
        $uploadedFile->moveTo($targetPath);

        
        $mediaType = explode('/', $uploadedFile->getClientMediaType());
        $uploadType = 'uploadFile';
        $msgType = 'file';
        switch ($mediaType[0]) {
            case 'image':
                $uploadType = 'uploadImage';
                $msgType = 'image';
                break;
            case 'video':
                $uploadType = 'uploadVideo';
                $msgType = 'video';
                break;
            case 'audio':
                $uploadType = 'uploadVoice';
                $msgType = 'voice';
                break;
        }
        
        if ($res = $weWork->media->{$uploadType}($targetPath)) {
            if (!$res['errcode']) {
                $mediaFiles[$msgType] = $res['media_id'];
            }
        }
        unlink($targetPath);
        return $targetPath;
    }

}