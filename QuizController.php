<?php

class QuizController extends Controller
{
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	public $layout='//layouts/column2';
        public $defaultAction = 'admin';

	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			'postOnly + delete', // we only allow deletion via POST request
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array(''),
				'users'=>array('*'),
			),
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array('index','view','create','update','admin','delete','winner'),
				'users'=>array('@'),
			),
			array('allow', // allow admin user to perform 'admin' and 'delete' actions
				'actions'=>array('dashboard','startQuiz'),
				'users'=>array('admin'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

	/**
	 * Displays a particular model.
	 * @param integer $id the ID of the model to be displayed
	 */
	public function actionView($id)
	{
		$this->render('view',array(
			'model'=>$this->loadModel($id),
		));
	}

	/**
	 * Creates a new model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 */
	public function actionCreate()
	{
		$model=new Quiz;

		// Uncomment the following line if AJAX validation is needed
		// $this->performAjaxValidation($model);

		if(isset($_POST['Quiz']))
		{
			$model->attributes=$_POST['Quiz'];
			if($model->save())
				$this->redirect(array('view','id'=>$model->quiz_id));
		}

		$this->render('create',array(
			'model'=>$model,
		));
	}

	/**
	 * Updates a particular model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 * @param integer $id the ID of the model to be updated
	 */
	public function actionUpdate($id)
	{
		$model=$this->loadModel($id);

		// Uncomment the following line if AJAX validation is needed
		// $this->performAjaxValidation($model);

		if(isset($_POST['Quiz']))
		{
			$model->attributes=$_POST['Quiz'];
			if($model->save())
				$this->redirect(array('view','id'=>$model->quiz_id));
		}

		$this->render('update',array(
			'model'=>$model,
		));
	}

	/**
	 * Deletes a particular model.
	 * If deletion is successful, the browser will be redirected to the 'admin' page.
	 * @param integer $id the ID of the model to be deleted
	 */
	public function actionDelete($id)
	{
		$this->loadModel($id)->delete();

		// if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
		if(!isset($_GET['ajax']))
			$this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('admin'));
	}

	/**
	 * Lists all models.
	 */
	public function actionIndex()
	{
		$dataProvider=new CActiveDataProvider('Quiz');
		$this->render('index',array(
			'dataProvider'=>$dataProvider,
		));
	}

	/**
	 * Manages all models.
	 */
	public function actionAdmin()
	{
		$model=new Quiz('search');
		$model->unsetAttributes();  // clear any default values
		if(isset($_GET['Quiz']))
			$model->attributes=$_GET['Quiz'];
		$this->render('admin',array(
			'model'=>$model,
		));
	}

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the ID of the model to be loaded
	 * @return Quiz the loaded model
	 * @throws CHttpException
	 */
	public function loadModel($id)
	{
		$model=Quiz::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}
        
        public function actionDashboard(){
            $this->layout='//layouts/column1';
            
            Yii::app()->user->setState("quiz_id",NULL);
            Yii::app()->user->setState("teamA_id",NULL);
            Yii::app()->user->setState("teamB_id",NULL);
            Yii::app()->user->setState("quiz_type",NULL);
            
            $teams = Team::model()->findAll();
            $this->render('dashboard',array("teams"=>$teams));
        }
        
        public function actionStartQuiz(){
            $this->layout='//layouts/column1';
            if(isset($_POST['teamA'])){
                $teamA = Team::model()->findByPk($_POST['teamA']);
                $teamB = Team::model()->findByPk($_POST['teamB']);
                
                //reseting team total score
                $teamA->total_score = 0;
                $teamA->save();
                $teamB->total_score = 0;
                $teamB->save();
                
                //loging quiz
                $quizModel = new Quiz();
                $quizModel->team1_id = $_POST['teamA'];
                $quizModel->team2_id = $_POST['teamB'];
                $quizModel->type     = $_POST['type'];
                $quizModel->save();
                $quiz_id = $quizModel->quiz_id;
            }else{
                header("Location: ".$this->createUrl("/quiz/dashboard"));
                exit();
            }
            
            $data['quiz_id'] = $quiz_id;
            $data['team_id'] = $teamA->team_id;
            
            //set session variables
            Yii::app()->user->setState("quiz_id",$quiz_id);
            Yii::app()->user->setState("teamA_id",$teamA->team_id);
            Yii::app()->user->setState("teamB_id",$teamB->team_id);
            if($_POST['type']=='Regular Quiz')
                Yii::app()->user->setState("quiz_type","regular_quiz");
            else
                Yii::app()->user->setState("quiz_type","death_match");
                    
            if($_POST['type']=='Regular Quiz'){
                $this->render('regularQuiz',array("teamA"=>$teamA,"teamB"=>$teamB,"quizId"=>$quiz_id));
            }else{
                $this->render('deathMatch',array("teamA"=>$teamA,"teamB"=>$teamB));
            }
        }
        
        public function actionWinner(){
            $quizId  = Yii::app()->user->getState("quiz_id");
            $teamAId = Yii::app()->user->getState("teamA_id");
            $teamBId = Yii::app()->user->getState("teamB_id");
            $type    = Yii::app()->user->getState("quiz_type");
            if($quizId && $teamAId && $teamBId && $type){
                Yii::app()->user->setState("quiz_id",NULL);
                Yii::app()->user->setState("teamA_id",NULL);
                Yii::app()->user->setState("teamB_id",NULL);
                Yii::app()->user->setState("quiz_type",NULL);
                
                $winner = Quiz::getWinner($quizId);
                if($winner=='tie'){
                    $this->render('tie',array("teamA"=>$teamAId,"teamB"=>$teamBId));
                }else{
                    $this->render('winner',array("winner"=>$winner));
                }
            }else{
                header("Location: ".$this->createUrl("/quiz/dashboard"));
                exit();
            }
        }

	/**
	 * Performs the AJAX validation.
	 * @param Quiz $model the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='quiz-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}
}
