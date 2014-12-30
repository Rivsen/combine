<?php

namespace Rswork\Silex\Combine;

class Combine
{
    protected $options = array(
        'base_path' => false,
        'cache_path' => 'cache',
        'css_path' => 'css',
        'js_path' => 'js',
    );

    public function __construct( $options )
    {
        $this->options['base_path'] = sys_get_temp_dir().DIRECTORY_SEPARATOR.'combine';
        $this->options['cache_path'] = sys_get_temp_dir().DIRECTORY_SEPARATOR.'combine'.DIRECTORY_SEPARATOR.'cache';
        $this->options = array_merge( $this->options, $options );
    }

    public function getInfo( $files, $base )
    {
        $options = $this->options;
        $type = substr($files, strrpos($files, '.') + 1);

        if( !in_array( $type, array('css', 'js') ) ) {
            return false;
        }

        $type_path = rtrim(
            realpath(
                rtrim(
                    rtrim($options['base_path'], '\/\\')
                    . DIRECTORY_SEPARATOR
                    . rtrim($options[$type.'_path'], '\/\\')
                , '\/\\')
                . DIRECTORY_SEPARATOR
                . trim($base, '\/\\')
            )
            ,'\/\\'
        );

        if( !file_exists( $type_path ) ) {
            if( !mkdir( $type_path ) ) {
                return false;
            }
        }

        if(
            !file_exists( $type_path )
            OR strrpos($type_path, $options['base_path'], -strlen($type_path)) === FALSE
        ) {
            return false;
        }

        $elements = explode(',', $files);
        $lastmodified = 0;

        while (list(,$element) = each($elements)) {
            $path = realpath($type_path . DIRECTORY_SEPARATOR . $element);

            if (file_exists($path)) {
                $lastmodified = max($lastmodified, filemtime($path));
            }
        }

        $hash = $lastmodified . '-' . md5($files);

        return array(
            'hash' => $hash,
            'lastmodified' => $lastmodified,
            'elements' => $elements,
            'type' => $type,
            'type_path' => $type_path,
        );
    }

    public function combine( $files, $base )
    {
        $options = $this->options;
        $lastmodified = 0;

        $result = array(
            'lastmodified'=> $lastmodified,
            'content' => '',
            'cache_file' => false,
            'etag' => false,
        );

        $info = $this->getInfo( $files, $base );

        if( $info === false ) {
            return false;
        }

        $type = $info['type'];
        $type_path = $info['type_path'];
        $elements = $info['elements'];
        $lastmodified = $info['lastmodified'];
        $hash = $info['hash'];
        $result['lastmodified'] = $lastmodified;
        $result['etag'] = $hash;

        $cachefile = 'cache-' . $hash . '.' . $type;

        if( file_exists( $options['cache_path'].DIRECTORY_SEPARATOR.$cachefile ) ) {
            $result['cache_file'] = $options['cache_path'].DIRECTORY_SEPARATOR.$cachefile;
            return $result;
        }

        $contents = '';
        reset($elements);

        while (list(,$element) = each($elements)) {
            $path = realpath($type_path . DIRECTORY_SEPARATOR . $element);
            if( !file_exists( $path ) ) {
                if( $type == 'js' ) {
                    $contents .= ";console.log('{$element} File not Found!');\n\n";
                } else {
                    $contents .= "/** {$element} not found! **/\n\n";
                }
            } else {
                if( $type == 'js' ) {
                    $contents .= ';'.file_get_contents($path).";\n\n";
                } else {
                    $contents .= file_get_contents($path)."\n\n";
                }
            }
        }

        if ($fp = fopen($options['cache_path'].DIRECTORY_SEPARATOR.$cachefile, 'wb')) {
            fwrite($fp, $contents);
            fclose($fp);
        }

        $result['cache_file'] = $options['cache_path'].DIRECTORY_SEPARATOR.$cachefile;
        return $result;
    }
}
