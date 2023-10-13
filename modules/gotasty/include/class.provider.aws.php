<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Database functionality for Reseller.
 * Date  19/05/2018.
 **/

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class aws {

    function __construct($db) {
        // $this->db      = $db;

    }

    public function awsUploadImage($params){

        include("config.php");

        $imgSrc = $params['imgSrc'] != "" ? $params['imgSrc'] : ""; 

        if($imgSrc){
            $s3Params=[
                'version' => 'latest',
                'region' => $config['doRegion'],
                'endpoint' => $config['doEndpoint'],
                'credentials' => [
                    'key'    => $config['doApiKey'],
                    'secret' => $config['doSecretKey'],
                ],
                'debug' => false,
            ];

            $s3 = new S3Client($s3Params);
            $imageSize = getimagesize($imgSrc);
            // echo $value;break;
            $image_parts = explode(";base64,", $imgSrc);
            $image_type_aux = explode("image/", $image_parts[0]);
            $imageType = $image_type_aux[1];
            $contentType = $image_type_aux[1];

            if(!$image_type_aux[1]){
                $image_type_aux = explode("application/", $image_parts[0]);
                $contentType = "application/".$image_type_aux[1];
                $imageType = $image_type_aux[1];
            }
            $image_base64 = base64_decode($image_parts[1]);

            $dateTime = new DateTime();
            $fileName = uniqid() . "." . $imageType;

            $putParams = [   
                 //'ContentLength'     => $imageSize,
                'ContentType'       => $contentType,
                'Bucket'            => $config['doBucketName'],
                'Key'               => $config['doFolderName'].$fileName, // this is the save as file in the space
                'Body'              => $image_base64, // and this is the file name on this server
                'ACL'               => 'public-read',
            ] ;

            try {
                $result = $s3->putObject($putParams);
                // $result->toArray();
            } catch (S3Exception $e) {
                $imageStatusMsg = $e;
            }

            return array('status' => "ok", 'code' => 0,  'statusMsg' => "Image uploaded successfully.", 'data' => '', 'imageUrl' => $result['ObjectURL']);

        }
        else{
            return array('status' => "error", 'code' => 1,  'statusMsg' => "No image was uploaded.", 'data' => '', 'response' => $result, 'imageStatusMsg' => json_encode($e));
        }
    }

    public function awsUploadVideo($params){

        include("config.php");

        $videoSrc = $params['videoSrc'] != "" ? $params['videoSrc'] : ""; 

        if($videoSrc){
            $s3Params=[
                'version' => 'latest',
                'region' => $config['doRegion'],
                'endpoint' => $config['doEndpoint'],
                'credentials' => [
                    'key'    => $config['doApiKey'],
                    'secret' => $config['doSecretKey'],
                ],
                'debug' => false,
            ];

            $s3 = new S3Client($s3Params);
            $imageSize = getimagesize($videoSrc);
            $video_parts = explode(";base64,", $videoSrc);
            $video_type_aux = explode("video/", $video_parts[0]);
            $videoType = $video_type_aux[1];
            $contentType = $video_type_aux[1];

            if(!$video_type_aux[1]){
                $video_type_aux = explode("application/", $video_parts[0]);
                $contentType = "application/".$video_type_aux[1];
                $videoType = $video_type_aux[1];
            }
            $video_base64 = base64_decode($video_parts[1]);

            $dateTime = new DateTime();
            $fileName = uniqid() . "." . $videoType;

            $putParams = [   
                 //'ContentLength'     => $imageSize,
                'ContentType'       => $contentType,
                'Bucket'            => $config['doBucketName'],
                'Key'               => $config['doFolderName'].$fileName, // this is the save as file in the space
                'Body'              => $video_base64, // and this is the file name on this server
                'ACL'               => 'public-read',
            ] ;

            try {
                $result = $s3->putObject($putParams);
                // $result->toArray();
            } catch (S3Exception $e) {
                $imageStatusMsg = $e;
            }

            return array('status' => "ok", 'code' => 0,  'statusMsg' => "Image uploaded successfully.", 'data' => '', 'videoUrl' => $result['ObjectURL']);

        }
        else{
            return array('status' => "error", 'code' => 1,  'statusMsg' => "No image was uploaded.", 'data' => '', 'response' => $result, 'videoStatusMsg' => json_encode($e));
        }
    }

    public function awsGeneratePreSignedUrl($params){

        include("config.php");

        $action = $params['action']; 

        if($action == 'upload' || $action == 'download'){
            $mimeType = $params['mimeType'] != "" ? $params['mimeType'] : "";

            if($mimeType == ''){
                return array('status' => "error", 'code' => 1,  'statusMsg' => "File Type Not Found.", 'data' => '',);
            }

            if($action == 'upload'){
                //upload generate fileName with video type
                $fileType = explode('/',$mimeType)[1];
                $fileName = uniqid() . "." . $fileType;
            }
            else{
                //download need name 
                $fileName = $params['fileName'] != "" ? $params['fileName'] : ""; 
                if($fileName == ''){
                    return array('status' => "error", 'code' => 1,  'statusMsg' => "File Name Not Found.", 'data' => '',);
                }
            }

            $s3Params=[
                'version' => 'latest',
                'region' => $config['doRegion'],
                'endpoint' => $config['doEndpoint'],
                'credentials' => [
                    'key'    => $config['doApiKey'],
                    'secret' => $config['doSecretKey'],
                ],
                'debug' => false,
            ];
            //Create S3 Client with params
            $s3 = new S3Client($s3Params);

            if($action == 'upload'){
                //upload presigned url
                try{
                    $command = $s3->getCommand('PutObject',[
                        'ContentType'       => $mimeType,
                        'Bucket'            => $config['doBucketName'],
                        'Key'               => $config['doFolderName'].$fileName,
                        'ACL'               => 'public-read',
                        ]
                    );
                }
                catch(S3Exception $e){
                    return array('status' => "error", 'code' => 1,  'statusMsg' => $e, 'data' => '');
                }
            }
            else{
                //download presigned url
                try{
                    $command = $s3->getCommand('GetObject',[
                        'ContentType'       => $mimeType,
                        'Bucket'            => $config['doBucketName'],
                        'Key'               => $config['doFolderName'].$fileName,
                        'ACL'               => 'public-read',
                        ]
                    );
                }
                catch(S3Exception $e){
                    return array('status' => "error", 'code' => 1,  'statusMsg' => $e, 'data' => '');
                }
            }


            try{
                $presignedRequest = $s3->createPresignedRequest($command, '+10 minutes');
            }
            catch(S3Exception $e){
                return array('status' => "error", 'code' => 1,  'statusMsg' => $e, 'data' => '');
            }

            try{
                $presignedUrl = (string) $presignedRequest->getUri();
                $result['ObjectURL'] = $presignedUrl;
            }
            catch(S3Exception $e){
                return array('status' => "error", 'code' => 1,  'statusMsg' => $e, 'data' => '');
            }


            $returnFileName = $fileName;

            return array('status' => "ok", 'code' => 0,  'statusMsg' => "Generate pre-signed URL successfully.", 'data' => $result['ObjectURL'], 'preSignedUrl' => $result['ObjectURL'], 'returnFileName' => $returnFileName);
            
        }
        else{
            return array('status' => "error", 'code' => 1,  'statusMsg' => "Invalid Action.", 'data' => '',);
        }       
    }

}
?>