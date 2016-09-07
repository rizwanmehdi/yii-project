<?php

class QuizServicesController extends Controller
{
	public function actionIndex()
	{
            $this->render('index');
	}

	public function beforeAction($action){
            if(!Yii::app()->request->isAjaxRequest)
                throw new Exception('404, page not found');
            
            return parent::beforeAction($action);
        }
        /*
         * verify and return answer status for regular quiz
         * with scorecard html and quiz staus
         */
        public function actionVerifyAnswer(){
            $questionId     = $_POST['question_id'];
            $quizId         = Yii::app()->user->getState("quiz_id");
            $teamAId        = Yii::app()->user->getState("teamA_id");
            $teamBId        = Yii::app()->user->getState("teamB_id");
            $answer         = $_POST['answer'];
            $turn           = $_POST['turn'];
            $status         = false;
            
            $transaction    = Yii::app()->db->beginTransaction();
            try
            {
                //verfying answer
                $question        = Question::model()->findByPk($questionId);
                $correctAnswers  = $question->correctAnswers;
                foreach($correctAnswers as $correctAnswer){
                    if($correctAnswer->option_id==$answer){
                        $status = true;
                        break;
                    }
                }
                //updating question status
                $question->answered = 1;
                if(!$question->save())
                    throw new Exception("Question not saved");

                //updating team total score
                $team = Team::model()->findByPk($turn=="teamA" ? $teamAId:$teamBId);
                $team->total_score = $team->total_score+($status ? Question::CORRECT_ANSWER_MAKS:Question::WRONG_ANSWER_MARKS);
                if(!$team->save())
                    throw new Exception("Team total score not updated");

                //check if team score already exist
                $teamScore = TeamScore::model()->findByAttributes(array("team_id"=>($turn=="teamA" ? $teamAId:$teamBId),"question_id"=>$questionId,"quiz_id"=>$quizId));
                if(count($teamScore)==0){
                    //adding team score
                    $teamScore = new TeamScore();
                    $teamScore->question_id     = $questionId;
                    $teamScore->quiz_id         = $quizId;
                }
                //check if question answered by other team
                if(isset($_POST['is_passed']) && $_POST['is_passed']==1)
                    $teamScore->team_id         = ($turn=="teamA" ? $teamBId:$teamAId);
                else
                    $teamScore->team_id         = ($turn=="teamA" ? $teamAId:$teamBId);
                
                $teamScore->score           = ($status ? Question::CORRECT_ANSWER_MAKS:Question::WRONG_ANSWER_MARKS);
                if(!$teamScore->save())
                    throw new Exception("Team score not added");
                
                //commiting transaction
                $transaction->commit();
            }catch(Exception $e){
               $transaction->rollback();
               die($e);
            }
            
            $scores = Quiz::getTeamScores($quizId, $teamAId, $teamBId);
            $teamA = Team::model()->findByPk($teamAId);
            $teamB = Team::model()->findByPk($teamBId);
            $result = array();
            
            if(Yii::app()->user->getState("quiz_type")=='regular_quiz'){
                //preparing score card
                $teamAName = explode('-', $teamA->name); $teamAName = ucfirst(trim($teamAName[0]));
                $teamBName = explode('-', $teamB->name); $teamBName = ucfirst(trim($teamBName[0]));
                $score_html = $this->renderPartial('regular_scorecard',array('teamAName'=>$teamAName,'teamBName'=>$teamBName,'scores'=>$scores,'turn'=>$turn),true);

                //check if quiz finished
                $total_questions = $scores['teamATotalQuestions'] + $scores['teamBTotalQuestions'];
                if($total_questions>=(Quiz::QUESTIONS_PER_QUIZ*2)){
                    $result['is_finished'] = 1;
                    if($scores['teamATotalScore']==$scores['teamBTotalScore'])
                        $result['winner_id'] = 'tie';
                    else
                        $result['winner_id'] = ($scores['teamATotalScore']>$scores['teamBTotalScore'] ? $teamA->team_id:$teamB->team_id);

                }else{
                    $result['is_finished'] = 0;
                    $result['winner_id'] = '';
                }

            }else{
                if($scores['teamATotalQuestions']==$scores['teamBTotalQuestions'] && $scores['teamATotalScore']==$scores['teamBTotalScore']){
                    if($status){
                        $teamAImgSrc = Yii::app()->theme->baseUrl."/img/green.png";
                        $teamBImgSrc = Yii::app()->theme->baseUrl."/img/green.png";
                    }else{
                        $teamAImgSrc = Yii::app()->theme->baseUrl."/img/red.png";
                        $teamBImgSrc = Yii::app()->theme->baseUrl."/img/red.png";
                    }
                    $result['is_finished'] = 0;
                    $result['winner_id'] = '';
                }elseif($scores['teamATotalQuestions']==$scores['teamBTotalQuestions'] && $scores['teamATotalScore']>$scores['teamBTotalScore']){
                    $teamAImgSrc = Yii::app()->theme->baseUrl."/img/green.png";
                    $teamBImgSrc = Yii::app()->theme->baseUrl."/img/red.png";
                    $result['is_finished'] = 1;
                    $result['winner_id'] = $teamA->team_id;
                }elseif($scores['teamATotalQuestions']==$scores['teamBTotalQuestions'] && $scores['teamATotalScore']<$scores['teamBTotalScore']){
                    $teamAImgSrc = Yii::app()->theme->baseUrl."/img/red.png";
                    $teamBImgSrc = Yii::app()->theme->baseUrl."/img/green.png";
                    $result['is_finished'] = 1;
                    $result['winner_id'] = $teamB->team_id;
                }elseif($scores['teamATotalQuestions']>$scores['teamBTotalQuestions']){
                    if($status)
                        $teamAImgSrc = Yii::app()->theme->baseUrl."/img/green.png";
                    else
                        $teamAImgSrc = Yii::app()->theme->baseUrl."/img/red.png";
                    
                    $teamBImgSrc = Yii::app()->theme->baseUrl."/img/yellow.png";
                    $result['is_finished'] = 0;
                    $result['winner_id'] = '';
                }
                $teamAName = explode('-', $teamA->name); $teamAName = ucfirst(trim($teamAName[0]));
                $teamBName = explode('-', $teamB->name); $teamBName = ucfirst(trim($teamBName[0]));
                $score_html = $this->renderPartial('death_match_scorecard',array('teamAName'=>$teamAName,'teamBName'=>$teamBName,'teamAImgSrc'=>$teamAImgSrc,'teamBImgSrc'=>$teamBImgSrc,'turn'=>$turn),true);
            }
            if($status)
                $result['status'] = "correct";
            else
                $result['status'] = "wrong";

            $result['score_card'] = $score_html;
            $correctIds = array();
            foreach($correctAnswers as $correctAnswer){
                $correctIds[] = $correctAnswer->option_id;
            }
            $result['correct_answers'] = $correctIds;
            
            //update quiz status
            if($result['is_finished']==1){
                $quiz = Quiz::model()->findByPk($quizId);
                if($quiz){
                    $quiz->status = 'completed';
                    $quiz->winner_team_id = ($result['winner_id'] != 'tie' ? $result['winner_id']:0);
                    $quiz->save();
                }
            }
            
            die(json_encode($result));
        }
        /*
         * store previous data and generate html for next question
         */
        public function actionNextQuestion(){
            $data['quiz_id'] = Yii::app()->user->getState("quiz_id");
            if(isset($_POST['turn']) && ($_POST['turn']=='teamA' || $_POST['turn']==''))
                $data['team_id'] = Yii::app()->user->getState("teamA_id");
            elseif(isset($_POST['turn']) && $_POST['turn']=='teamB')
                $data['team_id'] = Yii::app()->user->getState("teamB_id");
            
            $data['type']    = Yii::app()->user->getState("quiz_type");
            $question = Question::getQuestion($data);
            
            if($question){
                $html = '<div id="question-head">Question</div>
                        <div id="question">'.htmlentities($question->question).'</div>
                        <div class="get-ansrs-wrapper"><button class="get-answers btn btn-primary" id="yw0" name="yt0" type="button">Get answers</button></div>
                        <div id="answers"></div>';
                $result['html'] = $html;
                $result['question_id'] = $question->question_id;
            }else{
                $result['html'] = 'No new question found';
                $result['question_id'] = 0;
            }
            
            die(json_encode($result));
        }
        /*
         * get retireve answers
         */
        public function actionGetAnswers(){
            $question = Question::model()->findByPk($_POST['question_id']);
            $answers = $question->getAnswers();
            
            $count = 1;
            $optionTitle = array("A:","B:","C:","D:","E:","F:","G:","H:","I:","J:","K:");
            $options_html = '';
            foreach($answers as $answer){
                $options_html .= '<input id="radio'.$count.'" name="answer" type="radio" value="'.$answer->option_id.'" /><label for="radio'.$count.'">'.$optionTitle[$count-1].'&nbsp;&nbsp;&nbsp;'.htmlentities($answer->option).'</label>';
                $count++;
            }
            $result['html'] = $options_html;
            $minimumTime = Question::TIME_PER_QUESTION;
            $totalOptions = count($answers);
            $calcTime = 0;
            if($totalOptions>4){
                $additionalOptions = $totalOptions - 4;
                $calcTime = $minimumTime + ceil($additionalOptions * 7);
            }
            $time = $calcTime > $minimumTime ? $calcTime : $minimumTime;
            $result['time'] = $time;
            
            die(json_encode($result));
        }
        /*
         * When team do not answer within time
         * then this action works
         * and mark that wrong
         */
        public function actionTimeout(){
            $questionId     = $_POST['question_id'];
            $quizId         = Yii::app()->user->getState("quiz_id");
            $teamAId        = Yii::app()->user->getState("teamA_id");
            $teamBId        = Yii::app()->user->getState("teamB_id");
            $turn           = $_POST['turn'];
            
            $transaction    = Yii::app()->db->beginTransaction();
            try
            {
                //updating question status
                $question        = Question::model()->findByPk($questionId);
                $question->answered = 1;
                if(!$question->save())
                    throw new Exception("Question not saved");

                //check if team score already exist
                $teamScore = TeamScore::model()->findByAttributes(array("team_id"=>($turn=="teamA" ? $teamAId:$teamBId),"question_id"=>$questionId,"quiz_id"=>$quizId));
                if(count($teamScore)==0){
                    //adding team score
                    $teamScore = new TeamScore();
                    $teamScore->question_id     = $questionId;
                    $teamScore->quiz_id         = $quizId;
                }

                $teamScore->team_id         = ($turn=="teamA" ? $teamAId:$teamBId);
                $teamScore->score           = Question::WRONG_ANSWER_MARKS;
                if(!$teamScore->save())
                    throw new Exception("Team score not saved");
                
                $transaction->commit();
            }catch(Exception $e){
               $transaction->rollback();
               die($e);
            }
            
            $scores = Quiz::getTeamScores($quizId, $teamAId, $teamBId);
            $teamA = Team::model()->findByPk($teamAId);
            $teamB = Team::model()->findByPk($teamBId);
            $result = array();
            
            if(Yii::app()->user->getState("quiz_type")=='regular_quiz'){
                //preparing score card
                $teamAName = explode('-', $teamA->name); $teamAName = ucfirst(trim($teamAName[0]));
                $teamBName = explode('-', $teamB->name); $teamBName = ucfirst(trim($teamBName[0]));
                $score_html = $this->renderPartial('regular_scorecard',array('teamAName'=>$teamAName,'teamBName'=>$teamBName,'scores'=>$scores,'turn'=>($turn=="teamA" ? "teamB":"teamA")),true);

            }else{
                if($scores['teamATotalQuestions']==$scores['teamBTotalQuestions'] && $scores['teamATotalScore']==$scores['teamBTotalScore']){
                    $teamAImgSrc = Yii::app()->theme->baseUrl."/img/red.png";
                    $teamBImgSrc = Yii::app()->theme->baseUrl."/img/red.png";
                }elseif($scores['teamATotalQuestions']==$scores['teamBTotalQuestions'] && $scores['teamATotalScore']>$scores['teamBTotalScore']){
                    $teamAImgSrc = Yii::app()->theme->baseUrl."/img/green.png";
                    $teamBImgSrc = Yii::app()->theme->baseUrl."/img/red.png";
                }elseif($scores['teamATotalQuestions']==$scores['teamBTotalQuestions'] && $scores['teamATotalScore']<$scores['teamBTotalScore']){
                    $teamAImgSrc = Yii::app()->theme->baseUrl."/img/red.png";
                    $teamBImgSrc = Yii::app()->theme->baseUrl."/img/green.png";
                }elseif($scores['teamATotalQuestions']>$scores['teamBTotalQuestions']){
                    $teamAImgSrc = Yii::app()->theme->baseUrl."/img/red.png";
                    $teamBImgSrc = Yii::app()->theme->baseUrl."/img/yellow.png";
                }
                $teamAName = explode('-', $teamA->name); $teamAName = ucfirst(trim($teamAName[0]));
                $teamBName = explode('-', $teamB->name); $teamBName = ucfirst(trim($teamBName[0]));
                $score_html = $this->renderPartial('death_match_scorecard',array('teamAName'=>$teamAName,'teamBName'=>$teamBName,'teamAImgSrc'=>$teamAImgSrc,'teamBImgSrc'=>$teamBImgSrc,'turn'=>($turn=="teamA" ? "teamB":"teamA")),true);
            }

            $result['score_card'] = $score_html;
            die(json_encode($result));
            
        }
        /*
         * mark question as answered
         */
        function actionSkipQuestion(){
            $questionId     = $_POST['question_id'];
            $question = Question::model()->findByPk($questionId);
            $question->answered = 1;
            if($question->save())
                die("success");
        }
        
        /*
         * change status of any previous question
         */
        public function actionPrevQuestion(){
            
        }
}