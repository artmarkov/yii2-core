<?php

namespace frontend\modules\account\controllers;

use Yii;
use yii\base\InvalidParamException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use common\models\LoginForm;
use common\models\User;
use frontend\modules\account\models\PasswordResetRequestForm;
use frontend\modules\account\models\ResetPasswordForm;
use frontend\modules\account\models\SignupForm;

/**
 * Class UserController.
 */
class SignInController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['login', 'signup', 'confirm-email', 'request-password-reset', 'reset-password'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['login', 'signup', 'confirm-email', 'request-password-reset', 'reset-password'],
                        'allow' => false,
                        'roles' => ['@'],
                        'denyCallback' => function () {
                            return Yii::$app->controller->redirect(['default/view', 'id' => Yii::$app->user->id]);
                        },
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Logs in a user.
     *
     * @return mixed
     */
    public function actionLogin()
    {
        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            $model = Yii::$app->user->identity;
            $model->ip = Yii::$app->request->userIP;
            $model->save();

            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Logs out the current user.
     *
     * @return mixed
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Signs user up.
     *
     * @return mixed
     */
    public function actionSignup()
    {
        if (Yii::$app->keyStorage->get('frontend.registration')) {
            $model = new SignupForm();
            if ($model->load(Yii::$app->request->post())) {
                if ($user = $model->signup()) {
                    if (Yii::$app->keyStorage->get('frontend.email-confirm')) {
                        // подтверждение email
                        if ($model->sendEmail()) {
                            Yii::$app->session->setFlash('alert', [
                                'body' => Yii::t('frontend', 'Your account has been successfully created. Check your email for further instructions.'),
                                'options' => ['class' => 'alert-success'],
                            ]);
                        } else {
                            Yii::$app->session->setFlash('alert', [
                                'body' => Yii::t('frontend', 'There was an error sending email.'),
                                'options' => ['class' => 'alert-danger'],
                            ]);
                        }

                        return $this->refresh();
                    } else {
                        // автологин
                        if (Yii::$app->getUser()->login($user)) {
                            return $this->goHome();
                        }
                    }
                }
            }

            return $this->render('signup', [
                'model' => $model,
            ]);
        } else {
            Yii::$app->session->setFlash('alert', [
                'body' => Yii::t('frontend', 'Registration is disabled.'),
                'options' => ['class' => 'alert-info'],
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function actionConfirmEmail($id, $token)
    {
        $user = User::find()->where([
            'id' => $id,
            'access_token' => $token,
            'status'=> User::STATUS_INACTIVE,
        ])->one();

        if ($user) {
            $user->status = User::STATUS_ACTIVE;
            $user->removeAccessToken();
            $user->save();
            Yii::$app->session->setFlash('alert', [
                'body' => Yii::t('frontend', 'Your account has been successfully activated.'),
                'options' => ['class' => 'alert-success'],
            ]);
        } else {
            Yii::$app->session->setFlash('alert', [
                'body' => Yii::t('frontend', 'Error activate your account.'),
                'options' => ['class' => 'alert-danger'],
            ]);
        }

        return $this->goHome();
    }

    /**
     * Requests password reset.
     *
     * @return mixed
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->session->setFlash('alert', [
                    'body' => Yii::t('frontend', 'Check your email for further instructions.'),
                    'options' => ['class' => 'alert-success'],
                ]);
            } else {
                Yii::$app->session->setFlash('alert', [
                    'body' => Yii::t('frontend', 'Sorry, we are unable to reset password for email provided.'),
                    'options' => ['class' => 'alert-danger'],
                ]);
            }

            return $this->refresh();
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    /**
     * Resets password.
     *
     * @param string $token
     * @return mixed
     * @throws BadRequestHttpException
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->session->setFlash('alert', [
                'body' => Yii::t('frontend', 'New password was saved.'),
                'options' => ['class' => 'alert-success'],
            ]);

            return $this->goHome();
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }
}
