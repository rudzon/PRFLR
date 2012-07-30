<?php

class dispatcher
{
    private $mongo;
    private $data;

    public function __construct($connect)
    {
        //TODO  move to config
        $this->mongo = new Mongo($connect);
        $this->data = $this->mongo->prflr->timers;
    }

    public function __destruct()
    {
        $this->mongo->close();
    }

    private function out($data)
    {
        $dat = array();
        foreach ($data as $k => $item) {
            unset($item['_id']);
            $dat[] = $item;
        }
        return $dat;
    }

    //TODO  delete on production
    public function init()
    {
        $this->data->remove();
        for ($i = 0; $i < 100000; $i++) {
            $this->data->insert(array(
                'group' => 'group.' . rand(1, 9),
                'timer' => 'timer.' . rand(10, 99),
                'info' => 'info' . rand(1, 9),
                'thread' => 'somethread' . rand(1000000, 300000000),
                'time'=> array('current' => rand(8, 999)),
            ));
        }
        return array('add' => $i);
    }

    private function prepareCriteria()
    {
        $criteria = array();
        if (isset($_GET["filter"])) {
            $par = explode('/', $_GET["filter"]);
            if (isset($par[0]) && $par[0] != '*')
                $criteria['group'] = new MongoRegex("/" . $par[0] . "/i");
            if (isset($par[1]) && $par[1] != '*')
                $criteria['timer'] = new MongoRegex("/" . $par[1] . "/i");
            if (isset($par[2]) && $par[2] != '*')
                $criteria['info'] = new MongoRegex("/" . $par[2] . "/i");
            if (isset($par[3]) && $par[3] != '*')
                $criteria['thread'] = $par[3];
        }
        return $criteria;
    }

    private function prepareGroupBy()
    {
        if (isset($_GET['groupby'])) {
            $gb = explode(',', $_GET['groupby']);

            foreach ($gb as $key => $val)
                $keys[$val] = $key + 1;
        } else {
            $keys = array("timer" => 1, "group" => 2);
        }
        $initial = array("time" => array("min" => (int) 9999999, "max" => 0, "total" => 0), "count" => 0);
        $reduce = "function (obj, prev) {
prev.count++;
prev.time.total += obj.time.current;
if (prev.time.min > obj.time.current) prev.time.min = obj.time.current;
if (prev.time.max < obj.time.current) prev.time.max = obj.time.current;

}";

        return array($keys, $initial, $reduce);
    }

    public function stat_last()
    {
        $criteria = $this->prepareCriteria();
        $data = $this->data->find($criteria)->limit(50);
        return $this->out($data);
    }

    public function stat_aggregate()
    {
        $criteria = $this->prepareCriteria();
        $gr = $this->prepareGroupBy();
        $data = $this->data->group($gr[0], $gr[1], $gr[2], $criteria);

        if (isset($_GET["sortby"])) {

            //sort by  parameter   min/max/average/total/count/dispersion
            function sorter($a, $b)
            {
                $sort = $_GET["sortby"];
                if ($sort == 'count') {
                    $aa = $a[$sort];
                    $bb = $b[$sort];
                } elseif ($sort == "average") {
                    $aa = $a['time']['total'] / $a['count'];
                    $bb = $b['time']['total'] / $b['count'];
                } elseif ($sort == "dispersion") {
                    $aa = ($a['time']['max'] - $a['time']['min']) / ($a['time']['total'] / $a['count']);
                    $bb = ($a['time']['max'] - $a['time']['min']) / ($b['time']['total'] / $b['count']);
                } else {
                    $aa = $a['time'][$sort];
                    $bb = $b['time'][$sort];
                };
                if ($aa == $bb) {
                    return 0;
                }
                return ($aa > $bb) ? -1 : 1;
            }

            usort($data['retval'], 'sorter');
        }

        return $this->out($data['retval']);
    }

    public function stat_graph()
    {
        return array(
            array(
                'timer' => 'my.first.timer',
                'data' => array(
                    '0' => 12,
                    '1' => 15,
                    '2' => 17,
                ),
            ),
            array(
                'timer' => 'my.second.timer',
                'data' => array(
                    '0' => 22,
                    '1' => 17,
                    '2' => 10,
                ),
            ),
        );
    }

    public function settings()
    {
        return array(
            'store' => 60 * 15,
            'block' => array(
                'timers' => array(
                    'btime.rrt.*',
                    'mtime.*.ff',
                ),
                'groups' => array(
                    '127.0.0.*',
                    '192.168.0.*',
                ),
            ),
        );
    }

}

$d = new dispatcher("mongodb://prflr:prflr@188.127.227.36");
//$d = new dispatcher("mongodb://prflr:prflr@127.0.0.1/prflr");
$r = str_replace('/', '_', $_GET['r']);
eval('$r = $d->' . $r . '();');
echo json_encode($r);
unset($d);

