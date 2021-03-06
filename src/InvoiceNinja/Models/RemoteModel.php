<?php namespace InvoiceNinja\Models;

use Exception;
use InvoiceNinja\Config;

class RemoteModel
{
    protected static $route;
    protected static $include;
    protected static $options = [];

    public $id;

    /**
    * @return array
    */
    public static function all()
    {
        $url = static::getRoute();
        $data = static::sendRequest($url);

        $result = [];
        foreach ($data as $item) {
            $result[] = static::hydrate($item);
        }

        return $result;
    }

    /**
    * @return \InvoiceNinja\Models\RemoteModel
    */
    public static function find($id)
    {
        $url = static::getRoute() . '/' . $id;
        $data = static::sendRequest();

        return static::hydrate($data);
    }

    public static function whereClientId($clientId)
    {
        static::$options['client_id'] = $clientId;
    }

    /**
    * @return \InvoiceNinja\Models\RemoteModel
    */
    public function save()
    {
        $url = static::getRoute();

        if ($this->id) {
            $method = 'PUT';
            $url .= '/' . $this->id;
        } else {
            $method = 'POST';
        }

        $data = static::sendRequest($url, $this, $method);

        return static::hydrate($data, $this);
    }

    public function archive()
    {
        return $this->sendAction('archive');
    }

    public function restore()
    {
        return $this->sendAction('restore');
    }

    public function delete()
    {
        return $this->sendAction('delete');
    }

    protected function sendAction($action)
    {
        $url = sprintf('%s/%s?action=%s', static::getRoute(), $this->id, $action);

        $data = static::sendRequest($url, $this, 'PUT');

        return static::hydrate($data, $this);
    }

    protected static function getRoute()
    {
        if ( ! static::$route) {
            throw new Exception('API route is not defined for ' . get_called_class());
        }

        return sprintf('%s/%s', Config::getUrl(), static::$route);
    }

    protected static function hydrate($item, $entity = false)
    {
        if ( ! $entity) {
            $className = get_called_class();
            $entity = new $className();
        }

        foreach ($item as $key => $value) {
            $entity->$key = $value;
        }

        return $entity;
    }

    protected static function sendRequest($url, $data = false, $type = 'GET', $raw = false)
    {
        $data = json_encode($data);
        $curl = curl_init();

        $options = array_merge(static::$options, [
            'include' => static::$include,
            'per_page' => Config::getPerPage()
        ]);
        $url .= '?' . http_build_query($options);

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $type,
            CURLOPT_POST => $type === 'POST' ? 1 : 0,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data),
                'X-Ninja-Token: '. Config::getToken(),
            ],
        ];

        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);

        if ($raw) {
            return $response;
        } else {
            $json = json_decode($response);
            if ($json && property_exists($json, 'data')) {
                return $json->data;
            } else {
                throw new Exception($response);
            }
        }
    }

}
