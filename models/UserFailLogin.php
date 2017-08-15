<?php
/* Ограничение попыток входа по паролю (c) Sonys */

namespace budyaga\users\models;

use yii\db\ActiveRecord;
use yii\db\Expression;


/**
 * This is the model class for table "user_fail_login".
 *
 * @property integer $id
 * @property string $ip
 * @property string $date
 * @property integer $login
 * @property integer $count
 */
class UserFailLogin extends ActiveRecord
{
    public $IpCount = 10;
    public $LoginCount = 3;
    public $TimeBlock = 5; //в минутах
    public $LogTime = 1440; //в минутах

    const ERR_NONE = 0;
    const ERR_FROM_IP = 1;
    const ERR_FROM_LOGIN = 2;
    const ERR_OTHER = 3;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'user_fail_login';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['date'], 'safe'],
            [['count'], 'integer'],
            [['login'], 'string', 'max' => 32],
            [['ip'], 'string', 'max' => 32],
        ];
    }
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'ip' => 'Ip',
            'date' => 'Date',
            'login' => 'Login',
            'count' => 'Count',
        ];
    }
    public function CountByIp($ip)
    {
        return $this->find()->where(['ip' => $ip])->andWhere(['>', 'date', new Expression('NOW() - INTERVAL '. $this->TimeBlock .' MINUTE')])->sum('count');
    }
    public function CountByLogin($login)
    {
        return $this->find()->where(['login' => $login])->andWhere(['>', 'date', new Expression('NOW() - INTERVAL '. $this->TimeBlock .' MINUTE')])->sum('count');
    }
    public function GetCD($ip, $login)
    {
        return $this->find()
            ->where(['ip' => $ip])
            ->orWhere(['login' => $login])
            ->andWhere(['<', 'date', new Expression('NOW() - INTERVAL '. $this->TimeBlock .' MINUTE')])
            ->orderBy(['date' => SORT_DESC])
            ->limit(1);
    }
    public function CheckFails($ip, $login) {
        $this->deleteAll(['<', 'date', new Expression('NOW() - INTERVAL '. $this->LogTime .' MINUTE')]);
        if($this->CountByIp($ip) > $this->IpCount) {
            return [$this::ERR_FROM_IP, $this->GetCD($ip, $login)];
        }
        if($this->CountByLogin($login) > $this->LoginCount) {
            return [$this::ERR_FROM_LOGIN, $this->GetCD($ip, $login)];
        }
        return 0;
    }
    public function addFail($ip, $login) {
        if (($fail = $this->findOne(['ip' => $ip, 'login' => $login])) !== null) {
            $fail->count++;
            $fail->date = new Expression('NOW()');
            $fail->save();
        } else {
            $fail = new UserFailLogin();
            $fail->login = $login;
            $fail->ip = $ip;
            $fail->save();
        }
    }
}
