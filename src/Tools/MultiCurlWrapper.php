<?php

namespace Maximaster\ProductionCalendar\Tools;

use Exception;

class MultiCurlWrapper implements RequestInterface
{
    protected $curl;
    protected $curlConfig;
    protected $chs = [];

    public function __construct($config)
    {
        $this->curlConfig = $config;
    }

    /**
     * @param string[] $urls
     * @return array
     * @throws Exception
     */
    public function query(array $urls)
    {
        $this->initCurl($urls);
        $err = $this->execCurl();
        $this->closeCurl();

        $responses = [];

        foreach ($this->chs as $ch) {
            $cont = curl_multi_getcontent($ch);

            if ($cont) {
                $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $responses[$url] = $cont;
            }
        }

        if (!$responses) {
            throw new Exception('Curl error:' . PHP_EOL . join(PHP_EOL, $err));
        }

        return $responses;
    }

    public function setOptions(array $options)
    {
        $this->curlConfig = $options;
    }

    protected function execCurl()
    {
        if (!$this->curl) {
            return [];
        }

        $running = null;
        $errors = [];
        do {
            $status = curl_multi_exec($this->curl, $running);
            curl_multi_select($this->curl);

            if ($status > 0) {
                $errors[] = curl_multi_strerror($status);
            }

        } while ($running);

        return $errors;
    }

    protected function initCurl(array $urls)
    {
        $this->curl = curl_multi_init();
        foreach ($urls as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, $this->curlConfig);
            curl_multi_add_handle($this->curl, $ch);
            $this->chs[] = $ch;
        }
    }

    protected function closeCurl()
    {
        foreach ($this->chs as $ch) {
            curl_multi_remove_handle($this->curl, $ch);
        }

        curl_multi_close($this->curl);
    }
}
