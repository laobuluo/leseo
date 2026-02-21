<?php
    namespace lezaiyun\Leseo\Tools\S3\Api;

	use Aws\Credentials\Credentials;
    use Aws\Exception\InvalidRegionException;
    use Aws\S3\S3Client;
	use Aws\Exception\AwsException;

    // 避免与其它使用 AWS SDK 的插件冲突，仅当未加载时引入
    if ( ! class_exists( 'Aws\S3\S3Client' ) ) {
        require_once __DIR__ . '/sdk/aws-autoloader.php';
    }
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
			$options = is_array( $options ) ? $options : [];
			if ( ! $this->s3 ) {
                $endpoint = isset( $options['leseo-s3-endpoint'] ) ? trim( (string) $options['leseo-s3-endpoint'] ) : '';
                $skip_ssl = ! empty( $options['leseo-s3-skip-ssl'] ) && in_array( $options['leseo-s3-skip-ssl'], array( 'true', true, '1', 1 ), true );
                $client_options = array(
                    'version' => "latest",
                    'credentials' => new Credentials(
                        isset( $options['leseo-s3-accesskey'] ) ? $options['leseo-s3-accesskey'] : '',
                        isset( $options['leseo-s3-secretkey'] ) ? $options['leseo-s3-secretkey'] : ''
                    ),
                );
                if ( $skip_ssl ) {
                    $client_options['http'] = array( 'verify' => false );
                }

                if ( $endpoint === '' ) {
                    $client_options['region'] = empty( $options['leseo-s3-region'] ) ? "us-west-2" : $options['leseo-s3-region'];
                    try {
                        $this->s3 = new S3Client( $client_options );
                    } catch (InvalidRegionException $exception) {
                        $this->s3 = null;
                        file_put_contents( plugin_dir_path( __FILE__ ) . 'error.log', $exception->getMessage() . "\n", FILE_APPEND );
                    }
                } else {
                    $endpoint_url = $endpoint;
                    if ( strpos( $endpoint, 'http://' ) !== 0 && strpos( $endpoint, 'https://' ) !== 0 ) {
                        $endpoint_url = 'https://' . ltrim( $endpoint, '/' );
                    }
                    $client_options['region']  = isset( $options['leseo-s3-region'] ) ? $options['leseo-s3-region'] : 'us-east-1';
                    $client_options['endpoint'] = $endpoint_url;
                    try {
                        $this->s3 = new S3Client( $client_options );
                    } catch ( \Exception $e ) {
                        $this->s3 = null;
                        file_put_contents( plugin_dir_path( __FILE__ ) . 'error.log', $e->getMessage() . "\n", FILE_APPEND );
                    }
                }
			}
			$this->bucket = isset( $options['leseo-s3-bucket'] ) ? $options['leseo-s3-bucket'] : '';
		}

		/** 是否已成功创建 S3 客户端（Region 异常等会为 false） */
		public function isReady() {
			return $this->s3 !== null;
		}

		public function Upload($key, $localFilePath) {
	        if ( $this->s3 === null ) {
	            return;
	        }
	        if ( ! is_readable( $localFilePath ) ) {
	            file_put_contents( plugin_dir_path( __FILE__ ) . 'error.log', '[LeSeo S3] File not readable: ' . $localFilePath . "\n", FILE_APPEND );
	            return;
	        }
	        $params = [
	            'Bucket'      => $this->bucket,
	            'Key'         => $key,
	            'SourceFile'  => $localFilePath,
	        ];
	        // 不传 ACL：多数对象存储已禁用或限制对象 ACL，传 public-read 易导致 403，桶级策略控制公开读即可
	        try {
                $this->s3->putObject( $params );
	        } catch (AwsException $exception) {
                $msg = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $exception->getMessage() . ' (Key: ' . $key . ')' . "\n";
                file_put_contents( plugin_dir_path( __FILE__ ) . 'error.log', $msg, FILE_APPEND );
	        }
		}

		public function Delete($keys) {
	        if ( $this->s3 === null ) {
	            return;
	        }
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
	        if ( $this->s3 === null ) {
	            return false;
	        }
	        try {
				return $this->s3->doesObjectExist($this->bucket, $key);
	        } catch (AwsException $exception) {
                file_put_contents(plugin_dir_path( __FILE__ ) . 'error.log', $exception->getMessage() );
				return False;
	        }
		}

	}
