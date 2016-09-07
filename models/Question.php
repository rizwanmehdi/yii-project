<?php

/**
 * This is the model class for table "question".
 *
 * The followings are the available columns in table 'question':
 * @property integer $question_id
 * @property string $question
 * @property integer $type_id
 * @property string $correct_answer
 * @property integer $viewed
 * @property integer $answered
 * @property integer $time_limit
 *
 * The followings are the available model relations:
 * @property AnswerOptions[] $answerOptions
 */
class Question extends CActiveRecord
{
        const TIME_PER_QUESTION = 30; //set time in seconds
        const CORRECT_ANSWER_MAKS = 10; //marks for esach question
        const WRONG_ANSWER_MARKS = 0; //minus points for wrong answers
	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'question';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('type_id, viewed, answered, time_limit', 'numerical', 'integerOnly'=>true),
			array('correct_answer,created_by', 'length', 'max'=>100),
			array('question', 'required'),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('question_id, question, type_id, correct_answer, viewed, answered, time_limit,created_by', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
			'answerOptions' => array(self::HAS_MANY, 'AnswerOptions', 'question_id'),
			'correctAnswers' => array(self::HAS_MANY, 'AnswerOptions', 'question_id','condition'=>'is_correct=1'),
			'incorrectAnswers' => array(self::HAS_MANY, 'AnswerOptions', 'question_id','condition'=>'is_correct=0'),
			'questionTypes' => array(self::BELONGS_TO, 'QuestionTypes', 'type_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'question_id' => 'Question',
			'question' => 'Question',
			'type_id' => 'Type',
			'correct_answer' => 'Correct Answer',
			'viewed' => 'Viewed',
			'answered' => 'Answered',
			'time_limit' => 'Time Limit',
			'created_by' =>'Created By'
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * Typical usecase:
	 * - Initialize the model fields with values from filter form.
	 * - Execute this method to get CActiveDataProvider instance which will filter
	 * models according to data in model fields.
	 * - Pass data provider to CGridView, CListView or any similar widget.
	 *
	 * @return CActiveDataProvider the data provider that can return the models
	 * based on the search/filter conditions.
	 */
	public function search()
	{
		// @todo Please modify the following code to remove attributes that should not be searched.

		$criteria=new CDbCriteria;

		$criteria->compare('question_id',$this->question_id);
		$criteria->compare('question',$this->question,true);
                
                if(isset($this->type_id) && $this->type_id!=""){
                    $optionCriteria = new CDbCriteria();
                    $optionCriteria->select = 'type_id';
                    $optionCriteria->condition = '`title` like "%'.$this->type_id.'%"';
                    $answerOptions = QuestionTypes::model()->findAll($optionCriteria);
                    $typeIds = '';
                    foreach($answerOptions as $answerOption)
                        $typeIds .= $answerOption->type_id.",";
                    $typeIds = rtrim($typeIds, ",");
                    if($typeIds=="")
                        $typeIds = "''";
                    $criteria->condition = "type_id IN(".$typeIds.")";
                }
		$criteria->compare('correct_answer',$this->correct_answer,true);
		$criteria->compare('viewed',$this->viewed);
		$criteria->compare('answered',$this->answered);
		$criteria->compare('time_limit',$this->time_limit);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return Question the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
        /*
         * return answers after shuffle
         */
//        public function getAnswers(){
//            $allAnswers = $this->answerOptions;
//            
//            $correctAnswers     = $this->correctAnswers;
//            $incorrectAnswers   = $this->incorrectAnswers;
//            $finalAnswers       = array();
//            $answersCount       = count($allAnswers);
//            
//            shuffle($incorrectAnswers);
//            $incorrectCount = count($incorrectAnswers);
//            if($incorrectCount<4){
//                $finalAnswers = $incorrectAnswers;
//                $finalAnswers[$incorrectCount] = $correctAnswers[rand(0,(count($correctAnswers)-1))];
//            }else{
//                for($i=0;$i<4;$i++){
//                    $finalAnswers[] = $incorrectAnswers[$i];
//                }
//                $finalAnswers[rand(0,3)] = $correctAnswers[rand(0,(count($correctAnswers)-1))];
//            }
//            shuffle($finalAnswers);
//            return $finalAnswers;
//        }
        public function getAnswers(){
            return $this->answerOptions;
        }
        /*
         * return valid question
         */
        public static function getQuestion($data){
            if($data['type']=='regular_quiz'){
                $teamScores = TeamScore::model()->findAllByAttributes(array("quiz_id"=>$data['quiz_id'],"team_id"=>$data['team_id']));
                $askedQuestionTypes = array();
                foreach($teamScores as $teamScore){
                    $type = $teamScore->question->questionTypes;
                    $askedQuestionTypes[$type->title] = isset($askedQuestionTypes[$type->title]) ? ($askedQuestionTypes[$type->title]+1):1;
                }
                //getting type list
                $questionTypes = QuestionTypes::model()->findAll();
                $availableTypes = array();
                foreach($questionTypes as $questionType){
                    if($questionType->title=='Death Match Questions'){
                        $deathMatchQuestionId = $questionType->type_id;
                        continue;
                    }
                    if(isset($askedQuestionTypes[$questionType->title]) && $askedQuestionTypes[$questionType->title]<$questionType->quota)
                        $availableTypes[] = $questionType->type_id;    
                    elseif(!isset($askedQuestionTypes[$questionType->title]))
                        $availableTypes[] = $questionType->type_id;

                }
                $availableTypes = implode(',', $availableTypes);
                if($availableTypes=='')
                    $availableTypes="''";

                //go for fresh question
                $criteria = new CDbCriteria();
                $criteria->condition = 'viewed IS NULL AND answered IS NULL AND type_id IN ('.$availableTypes.')';
                $criteria->order = 'RAND()';
                $criteria->limit = 1;
                $question = Question::model()->find($criteria);
                if(count($question)==0){
                    //go for un-answered question
                    $criteria = new CDbCriteria();
                    $criteria->condition = 'answered IS NULL AND type_id IN ('.$availableTypes.')';
                    $criteria->order = 'RAND()';
                    $criteria->limit = 1;
                    $question = Question::model()->find($criteria);
                    if(count($question)==0){
                        //go for any question available except death match
                        $criteria = new CDbCriteria();
                        $criteria->condition = 'answered IS NULL AND type_id!="'.$deathMatchQuestionId.'"';
                        $criteria->order = 'RAND()';
                        $criteria->limit = 1;
                        $question = Question::model()->find($criteria);
                        if(count($question)==0){
                            //go for death match question
                            $criteria = new CDbCriteria();
                            $criteria->condition = 'answered IS NULL AND type_id="'.$deathMatchQuestionId.'"';
                            $criteria->order = 'RAND()';
                            $criteria->limit = 1;
                            $question = Question::model()->find($criteria);
                        }
                    }
                }
            }else{
                //getting type list
                $deathMatchType = QuestionTypes::model()->findByAttributes(array('title'=>'Death Match Questions'));
                
                //go for fresh question
                $criteria = new CDbCriteria();
                $criteria->condition = 'viewed IS NULL AND answered IS NULL AND type_id="'.$deathMatchType['type_id'].'"';
                $criteria->order = 'RAND()';
                $criteria->limit = 1;
                $question = Question::model()->find($criteria);
                if(count($question)==0){
                    //go for un-answered question
                    $criteria = new CDbCriteria();
                    $criteria->condition = 'answered IS NULL AND type_id="'.$deathMatchType['type_id'].'"';
                    $criteria->order = 'RAND()';
                    $criteria->limit = 1;
                    $question = Question::model()->find($criteria);
                    if(count($question)==0){
                        //go for any question available
                        $criteria = new CDbCriteria();
                        $criteria->condition = 'answered IS NULL';
                        $criteria->order = 'RAND()';
                        $criteria->limit = 1;
                        $question = Question::model()->find($criteria);
                    }
                }
            }
            
            if($question){
                $question->viewed = 1;
                $question->save();
                return $question;
            }else
                return false;
        }
        /*
         * return questions count category wise
         */
        public static function getAvailableQuestionsCount(){
            $questionTypes = QuestionTypes::model()->findAll();
            $result = array();
            foreach($questionTypes as $type){
                $count = 0;
                foreach($type->questions as $question){
                    if($question->answered===null)
                        $count++;
                }
                $result[$type['title']] = $count;
            }
            return $result;
        }
        /*
         * return questions count answered wise
         */
        public static function getQuestionsCountStats(){
            $result = array();
            $result['Total Questions']      = Question::model()->count();
            $result['Answered/Skipped Questions']   = Question::model()->count("answered='1'");
            $result['UnAnswered Questions'] = Question::model()->count("answered IS NULL");
            $quizes                         = Quiz::model()->findAll();
            $correctAnswers   = 0;
            $incorrectAnswers = 0;
            foreach($quizes as $quiz){
                $correctAnswers   += count($quiz->correctAnswers);
                $incorrectAnswers += count($quiz->incorrectAnswers);
            }
            $result['Correct Count']        = $correctAnswers;
            $result['Wrong Count']          = $incorrectAnswers;
            
            return $result;
        }
}