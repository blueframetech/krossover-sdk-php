<?php
namespace Krossover\Models;

class Model
{
    /**
     * @param bool $scalarOnly
     * @return array
     * @throws \Exception
     */
    public function toArray($scalarOnly = false)
    {
        $vars = get_object_vars($this);
        return $this->varsToArray($vars, $scalarOnly);
    }

    /**
     * @param array $vars
     * @param $scalarOnly
     * @return array
     * @throws \Exception
     */
    protected function varsToArray(array $vars, $scalarOnly)
    {
        $returnArray = [];
        foreach ($vars as $property => $value) {
            $key = $property;
            if (is_scalar($value) || is_null($value)) {
                $returnArray[$key] = $value;
            } elseif ($value instanceof \DateTime) {
                $returnArray[$key] = empty($value) ? null : $value->format(\DateTime::W3C);
            } elseif (!$scalarOnly) {
                if (is_array($value)) {
                    $returnArray[$key] = $this->varsToArray($value, $scalarOnly);
                } elseif ($value instanceof \stdClass) {
                    $returnArray[$key] = $value;
                } else {
                    throw new \Exception('Unexpected object type, expecting an instance of Models\Base');
                }
            }
        }
        return $returnArray;
    }
}
