<?php


    namespace App\Containers;



    class PhpMyAdmin extends Container{
        protected string $service_name = 'phpmyadmin';

        protected array $service_definition = [
            'restart'     => 'unless-stopped',
            'expose'      => [80],
            'image'       => 'phpmyadmin/phpmyadmin',
            'environment' => [
                'MYSQL_ROOT_PASSWORD'=>'root',
                'PMA_HOST'=>'mysql',
                'UPLOAD_LIMIT'=> 3000000000,
            ],
        ];

        /**
         * @param string $service_name
         */
        public function set_database_service($service_name='mysql'){
            $this->set_environment("PMA_HOST", $service_name);
        }

        /**
         * @param string $password
         */
        public function set_database_root_password(string $password = 'root'){
            $this->set_environment('MYSQL_ROOT_PASSWORD', $password);
        }
    }
