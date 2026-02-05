<?php
    namespace lezaiyun\Leseo\Tools\S3\Api;

	use Aws\Credentials\Credentials;
    use Aws\Exception\InvalidRegionException;
    use Aws\S3\S3Client;
	use Aws\Exception\AwsException;

    require 'sdk/aws-autoloader.php';
	class LeseoS3Api
	{
		private $s3;
		private $bucket;  // = 'laojiang';

		public function __construct( $options = [
			'version' => "latest",
			'leseo-s3-region' => "us-west-2",
			'leseo-s3-accesskey' => "",
			'leseo-s3-secretkey' => "",
		] )
		{
			if ( !$this->s3 ) {
                if (empty($options['leseo-s3-endpoint'])) {
                    try {
                        $this->s3 = new S3Client([
                            'version' => "latest",  //版本
                            'region'  => empty($options['leseo-s3-region']) ? "us-west-2" : $options['leseo-s3-region'],  //区域
                            'credentials' => new Credentials($options['leseo-s3-accesskey'], $options['leseo-s3-secretkey'])
                        ]);
                    } catch (InvalidRegionException $exception) {
                        $this->s3 = null;
                        file_put_contents(plugin_dir_path( __FILE__ ) . 'error.log', $exception->getMessage() );
                    }
                } else {
                    $this->s3 = new S3Client([
                        'version' => "latest",    //版本
                        'region'  => $options['leseo-s3-region'],     //区域
                        'endpoint' => $options['leseo-s3-endpoint'],  //EndPoint
                        'credentials' => new Credentials($options['leseo-s3-accesskey'], $options['leseo-s3-secretkey'])
                    ]);
                }
			}
			$this->bucket = $options['leseo-s3-bucket'];
		}

		public function Upload($key, $localFilePath) {
	        try {
                $this->s3->putObject([
                    'Bucket' => $this->bucket,
                    'Key' => $key,
                    'SourceFile' => $localFilePath,
                    'ACL' => 'public-read'
                ]);
	        } catch (AwsException $exception) {
                file_put_contents(plugin_dir_path( __FILE__ ) . 'error.log', $exception->getMessage() );
	        }
		}

		public function Delete($keys) {
	        try {
                $objects = [];
                foreach ($keys as $key) {
                    $objects[] = [
                        'Key' => $key,
                    ];
                }

	            $this->s3->deleteObjects([
	                'Bucket' => $this->bucket,
	                'Delete' => [
	                    'Objects' => $objects,
	                ],
	            ]);
	        } catch (AwsException $exception) {
                file_put_contents(plugin_dir_path( __FILE__ ) . 'error.log', $exception->getMessage() );
	        }

		}

		public function hasExist($key) {
	        try {
				return $this->s3->doesObjectExist($this->bucket, $key);
	        } catch (AwsException $exception) {
                file_put_contents(plugin_dir_path( __FILE__ ) . 'error.log', $exception->getMessage() );
				return False;
	        }
		}

	}
