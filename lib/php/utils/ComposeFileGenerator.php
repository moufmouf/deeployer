<?php


namespace App\utils;


class ComposeFileGenerator
{
    public const TmpFilePath = '/tmp/deeployer.json';
    public const volumesToGenerate = '';



    public function createFile(array $deeployerConfig): string
    {
        $dockerFileConfig = $this->createDockerComposeConfig($deeployerConfig);

        // Affectation de deeployer.json à dockerFileConfig
        $returnCode = file_put_contents(self::TmpFilePath, json_encode($dockerFileConfig));

        // Checking if the content of TmpFilePath is well encoded
        if ($returnCode === false) {
            throw new \RuntimeException('Error when trying to create the docker-compose file');
        }
        return self::TmpFilePath;
    }

    public function createTraefikConf(): array
    {
        //todo allow configuration of the traefik config?
        return [
            "image" => "traefik:2.0",
            "command" => [
                "--providers.docker",
                "--providers.docker.exposedByDefault=false",
            ],
            "ports" => [
                "80:80",
            ],
            "volumes" => [
                "/var/run/docker.sock:/var/run/docker.sock",
            ]
        ];
    }

    public function createTraefikLabels(array $hostConfig): array
    {
        $host = $hostConfig['url'];
        // Utilité de cette ligne???
        return [
            'traefik.enable=true',
            "traefik.http.routers.front_router.rule=Host(`$host`)"
        ];
    }

    public function createServiceConfig(array $containerConfig): array
    {
        //createTraefikConf();

        //todo app more options
        $dockerComposeConfig = [
            'image' => $containerConfig['image']
        ];

        // Adding traefik_labels to containers
        //if (isset ($containerConfig['host'])) {
        //    $dockerComposeConfig['labels'] = [];
        //    foreach ($containerConfig['host']['url'] as $hostName => $hostNameUrl) {
        //        $dockerComposeConfig['labels'][$hostName] = $hostNameUrl ;
        //    }
        //}

        //Added ports
        if (isset($containerConfig['ports'])) {
            $dockerComposeConfig['ports'] = [];
            foreach ($containerConfig['ports'] as $portsSet => $portsValue) {
                $dockerComposeConfig['ports'][$portsSet] = $portsSet;
            }
        }

        if (isset($containerConfig['env'])) {
            $dockerComposeConfig['environment'] = [];
            foreach ($containerConfig['env'] as $envVariableName => $envVariableValue) {
                $dockerComposeConfig['environment'][$envVariableName] = $envVariableValue;
            }
        }

        // Set volumes for container
        if (isset($containerConfig['volumes'])) {
            $dockerComposeConfig['volumes'] = [];
            foreach ($containerConfig['volumes'] as $volumeName => $volume) {
                 $mountPath = $volume['mountPath'];
                 $dockerComposeConfig['volumes'][] = $volumeName.":".$mountPath;
            }
        }

        return $dockerComposeConfig;
    }

    // Create volumes
    public function createVolumeConfig( array $deeployerConfig ): array
    {
        $driver = ['driver' => 'local'];
        $volumesConfig = [] ;
        foreach ($deeployerConfig['containers'] as $serviceName => $containerConfig) {
            if (isset($containerConfig['volumes'])) {
                foreach ($containerConfig['volumes'] as $volumeName ) {
                    $volumesConfig[$volumeName] = $driver;
                }
            }
        }
        return $volumesConfig;
        // Need to make the returned value accessible
    }

    public function createDockerComposeConfig(array $deeployerConfig): array
    {
        $dockerComposeConfig = [];

        $dockerComposeConfig['version'] = "3.3";

        $dockerComposeConfig['services'] = [
            'traefik' => $this->createTraefikConf()
        ];
        foreach ($deeployerConfig['containers'] as $serviceName => $containerConfig) {
            $serviceConfig = $this->createServiceConfig($containerConfig);

            if (isset($containerConfig['host'])) {
                $serviceConfig['labels'] = $this->createTraefikLabels($containerConfig['host']);
            }

            $dockerComposeConfig['services'][$serviceName] = $serviceConfig;
        }

        $volumesConfig = $this->createVolumeConfig($deeployerConfig) ; // Need to put this in a variable

        $dockerComposeConfig['volumes'] = $volumesConfig ;

        return $dockerComposeConfig;
    }
}