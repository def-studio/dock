<?php /** @noinspection LaravelFunctionsInspection */

/** @noinspection DuplicatedCode */


namespace App\Recipes\PlainPhp;

    use App\Containers\MailHog;
    use App\Containers\Nginx;
    use App\Containers\Node;
    use App\Containers\PhpMyAdmin;
    use App\Containers\Composer;
    use App\Containers\MySql;
    use App\Exceptions\ContainerException;
    use App\Exceptions\DuplicateNetworkException;
    use App\Exceptions\DuplicateServiceException;
    use App\Recipes\DockerComposeRecipe;
    use App\Containers\Php;
    use App\Recipes\ReverseProxy\ReverseProxyRecipe;
    use Illuminate\Console\Command;
    use Illuminate\Contracts\Container\BindingResolutionException;

    class PlainPhpRecipe extends DockerComposeRecipe{
        const LABEL = 'PlainPhp';

        const DEFAULT_HOST = 'php.ktm';

        public function customize_init(Command $parent_command, string $env_content): string{

            if($parent_command->confirm('Would you like to customize your recipe?')){

                //<editor-fold desc="General Configuration">
                $parent_command->question("General configuration");

                $application_host = $parent_command->ask("Enter application hostname", self::DEFAULT_HOST);
                $this->set_env($env_content, "HOST", $application_host);

                $application_env = $parent_command->choice("Enter application environment", [
                    'local',
                    'production',
                ], 0);
                $this->set_env($env_content, "ENV", $application_env);
                //</editor-fold>


                $php_version = $parent_command->ask("Enter PHP version", 'latest');
                $this->set_env($env_content, "PHP_VERSION", $php_version);


                //<editor-fold desc="Network Configuration">
                $parent_command->question("Network configuration");
                if($parent_command->confirm("Is the application behind a proxy?")){

                    $nginx_root = $parent_command->ask("Enter Nginx root", '/var/www');
                    $this->set_env($env_content, "NGINX_ROOT", $nginx_root);

                    $this->comment_env($env_content, 'NGINX_PORT');
                    $this->comment_env($env_content, 'NGINX_PORT_SSL');
                    $this->comment_env($env_content, 'MYSQL_PORT');
                    $this->comment_env($env_content, 'PHPMYADMIN_PORT');
                    $this->comment_env($env_content, 'MAILHOG_PORT');
                    $this->set_env($env_content, 'REVERSE_PROXY_NETWORK', ReverseProxyRecipe::PROXY_NETWORK);
                } else{

                    $parent_command->info("Exposed services selection (type x to skip)");

                    $nginx_port = $parent_command->ask("Enter Nginx exposed port", 80);
                    if($nginx_port == 'x'){
                        $this->comment_env($env_content, 'NGINX_PORT');
                    } else{
                        if($nginx_port == '443'){
                            $this->comment_env($env_content, 'NGINX_PORT');
                            $this->set_env($env_content, "NGINX_PORT_SSL", $nginx_port);
                        } else{
                            $this->set_env($env_content, "NGINX_PORT", $nginx_port);

                            $nginx_ssl_port = $parent_command->ask("Enter Nginx SSL exposed port", 443);
                            if($nginx_ssl_port == 'x'){
                                $this->comment_env($env_content, 'NGINX_PORT_SSL');
                            } else{
                                $this->set_env($env_content, "NGINX_PORT_SSL", $nginx_ssl_port);
                            }
                        }
                    }



                    $mysql_port = $parent_command->ask("Enter MySQL exposed port", 3306);
                    if($mysql_port == 'x'){
                        $this->comment_env($env_content, 'MYSQL_PORT');
                    } else{
                        $this->set_env($env_content, "MYSQL_PORT", $mysql_port);
                    }

                    $phpmyadmin_port = $parent_command->ask("Enter PhpMyAdmin exposed port", 8081);
                    if($phpmyadmin_port == 'x'){
                        $this->comment_env($env_content, 'PHPMYADMIN_PORT');
                    } else{
                        $this->set_env($env_content, "PHPMYADMIN_PORT", $phpmyadmin_port);
                    }



                    $mailhog_port = $parent_command->ask("Enter MailHog exposed port", 8025);
                    if($mailhog_port == 'x'){
                        $this->comment_env($env_content, 'MAILHOG_PORT');
                    } else{
                        $this->set_env($env_content, "MAILHOG_PORT", $mailhog_port);
                    }


                }
                //</editor-fold>


                $mailhog_subdomain = $parent_command->ask("Enter MailHog exposed subdomain", "mail");
                if($mailhog_subdomain == 'x'){
                    $this->comment_env($env_content, 'MAILHOG_SUBDOMAIN');
                } else{
                    $this->set_env($env_content, "MAILHOG_SUBDOMAIN", $mailhog_subdomain);
                }


                $phpmyadmin_subdomain = $parent_command->ask("Enter PhpMyAdmin exposed subdomain", "mysql");
                if($phpmyadmin_subdomain == 'x'){
                    $this->comment_env($env_content, 'PHPMYADMIN_SUBDOMAIN');
                } else{
                    $this->set_env($env_content, "PHPMYADMIN_SUBDOMAIN", $phpmyadmin_subdomain);
                }


                //<editor-fold desc="MySql Configuration">
                $parent_command->question("MySql configuration");
                $this->set_env($env_content, 'MYSQL_DATABASE', $parent_command->ask("Database Name", "database"));
                $this->set_env($env_content, 'MYSQL_USER', $parent_command->ask("Database User", "dbuser"));
                $this->set_env($env_content, 'MYSQL_PASSWORD', $parent_command->ask("Database Password", "dbpassword"));
                $this->set_env($env_content, 'MYSQL_ROOT_PASSWORD', $parent_command->ask("Database Root Password", "root"));
                //</editor-fold>

            }

            return $env_content;
        }

        protected function recipe_commands(): array{
            return [

            ];
        }

        protected function host(): string{
            return env('HOST', self::DEFAULT_HOST);
        }

        protected function internal_network(): string{
            return "{$this->host()}_internal_network";
        }

        /**
         * @throws BindingResolutionException
         */
        public function build(){
            $nginx = $this->build_nginx();
            $mysql = $this->build_mysql();

            $this->build_phpmyadmin($mysql, $nginx);

            $this->build_mailhog($nginx);

            $this->add_container(Composer::class)->add_network($this->internal_network());

            $this->add_container(Node::class)->add_network($this->internal_network());

        }


        /**
         * @return Nginx
         * @throws BindingResolutionException
         */
        private function build_nginx(): Nginx{
            /** @var Php $php */
            $php = app()->make(Php::class)->add_network($this->internal_network());

            if(env('ENV', 'local') == 'local'){
                $php->enable_xdebug();
            }

            if(env('ENABLE_LIBREOFFICE_WRITER', '0') == '1'){
                $php->enable_libreoffice_writer();
            }

            $php->set_version(env('PHP_VERSION', 'latest'));


            /** @var Nginx $nginx */
            $nginx = $this->add_container(Nginx::class)->add_network($this->internal_network());
            $nginx->set_php_service($php);


            $nginx_root = env('NGINX_ROOT', '/var/www');

            $nginx->add_site($this->host(), 80, $nginx_root, null, null, '
                location /socket.io {
                    proxy_pass http://localhost:6001;
                    proxy_http_version 1.1;
                    proxy_set_header Upgrade $http_upgrade;
                    proxy_set_header Connection "Upgrade";
                }
            ');
            $nginx->add_site($this->host(), 443, $nginx_root, null, null, '
                location /socket.io {
                    proxy_pass http://localhost:6001;
                    proxy_http_version 1.1;
                    proxy_set_header Upgrade $http_upgrade;
                    proxy_set_header Connection "Upgrade";
                }
            ');


            if(!empty(env('NGINX_PORT'))){
                $nginx->map_port(env('NGINX_PORT'), 80);
                $this->add_exposed_host(env('HOST', self::DEFAULT_HOST));
                $this->add_exposed_address(self::LABEL, "http", env('HOST', self::DEFAULT_HOST), env('NGINX_PORT'));
            }
            if(!empty(env('NGINX_PORT_SSL'))){
                $nginx->map_port(env('NGINX_PORT_SSL'), 443);
                $this->add_exposed_host(env('HOST', self::DEFAULT_HOST));
                $this->add_exposed_address(self::LABEL . " SSL", "https", env('HOST', self::DEFAULT_HOST), env('NGINX_PORT_SSL'));
            }

            $proxy_network = env('REVERSE_PROXY_NETWORK');
            if(!empty($proxy_network)){
                $nginx->add_network($proxy_network);
            }

            return $nginx;
        }

        /**
         * @return MySql
         * @throws BindingResolutionException
         */
        private function build_mysql(): MySql{
            /** @var MySql $mysql */
            $mysql = $this->add_container(MySql::class)->add_network($this->internal_network());

            $mysql->set_database(env('MYSQL_DATABASE', 'database'));
            $mysql->set_user(env('MYSQL_USER', 'dbuser'));
            $mysql->set_password(env('MYSQL_PASSWORD', 'dbpassword'));
            $mysql->set_root_password(env('MYSQL_ROOT_PASSWORD', 'root'));

            if(!empty(env('MYSQL_PORT'))){
                $this->add_exposed_host(env('HOST', self::DEFAULT_HOST));
                $this->add_exposed_address("MySql", "http", env('HOST', self::DEFAULT_HOST), env('MYSQL_PORT'));
                $mysql->map_port(env('MYSQL_PORT'), 3306);
            }

            return $mysql;
        }

        /**
         * @param MySql $mysql
         * @param Nginx $nginx
         *
         * @return PhpMyAdmin
         * @throws BindingResolutionException
         */
        public function build_phpmyadmin(MySql $mysql, Nginx $nginx): ?PhpMyAdmin{

            if(env('ENV', 'local') != 'local') return null;
            if(empty(env("PHPMYADMIN_PORT")) && empty(env("PHPMYADMIN_SUBDOMAIN"))) return null;


            /** @var PhpMyAdmin $phpmyadmin */
            $phpmyadmin = $this->add_container(PhpMyAdmin::class)->add_network($this->internal_network());
            $phpmyadmin->set_database_service($mysql->service_name());
            $phpmyadmin->set_database_root_password($mysql->get_environment('MYSQL_ROOT_PASSWORD', 'root'));
            $phpmyadmin->depends_on($mysql->service_name());

            if(!empty(env("PHPMYADMIN_PORT"))){
                $phpmyadmin->map_port(env("PHPMYADMIN_PORT"), 80);
                $this->add_exposed_host(env('HOST', self::DEFAULT_HOST));
                $this->add_exposed_address("PhpMyAdmin", "http", env('HOST', self::DEFAULT_HOST), env('PHPMYADMIN_PORT'));
            }

            if(!empty(env("PHPMYADMIN_SUBDOMAIN"))){
                $host = env('PHPMYADMIN_SUBDOMAIN') . "." . env('HOST');
                $nginx->add_proxy($host, 80, $phpmyadmin->service_name(), 80);
                $this->add_exposed_host($host);
                $this->add_exposed_address("PhpMyAdmin ", "http", $host, 80);
            }

            return $phpmyadmin;
        }

        /**
         * @param Nginx $nginx
         *
         * @return MailHog
         * @throws BindingResolutionException
         */
        public function build_mailhog(Nginx $nginx): ?MailHog{

            if(env('ENV', 'local') != 'local') return null;
            if(empty(env("MAILHOG_PORT")) && empty(env("MAILHOG_SUBDOMAIN"))) return null;


            /** @var MailHog $mailhog */
            $mailhog = $this->add_container(MailHog::class)->add_network($this->internal_network());

            if(!empty(env("MAILHOG_PORT"))){
                $mailhog->map_port(env("MAILHOG_PORT"), 8025);
                $this->add_exposed_host(env('HOST', self::DEFAULT_HOST));
                $this->add_exposed_address("MailHog", "http", env('HOST', self::DEFAULT_HOST), env('MAILHOG_PORT'));
            }

            if(!empty(env("MAILHOG_SUBDOMAIN"))){
                $host = env('MAILHOG_SUBDOMAIN') . "." . env('HOST');
                $nginx->add_proxy($host, 80, $mailhog->service_name(), 8025);
                $this->add_exposed_host($host);
                $this->add_exposed_address("MailHog ", "http", $host, 80);
            }

            return $mailhog;
        }


        /**
         * @throws ContainerException
         * @throws DuplicateNetworkException
         * @throws DuplicateServiceException
         */
        public function setup(){
            parent::setup();

            $this->docker_service->add_network($this->internal_network(), $this->internal_network(), 'bridge');

            $proxy_network = env('REVERSE_PROXY_NETWORK');
            if(!empty($proxy_network)){
                $this->docker_service->add_external_network($proxy_network);
            }
        }

    }
