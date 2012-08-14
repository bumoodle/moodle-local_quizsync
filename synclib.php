<?php

/**
 * Binghamton University modifications which allow for synchronization of a quiz between partners, on submit. 
 * 
 * @package BU_QTYPE_PARTNER
 * @version $id$
 * @copyright 2011, 2012 Binghamton University
 * @author Kyle Temkin <ktemkin@binghamton.edu> 
 * @license GNU Public License, {@link http://www.gnu.org/copyleft/gpl.html}
 */
class quiz_synchronization
{
    public function handle_sync_if_necessary(quiz_attempt $attempt)
    {
        //determine if the quiz needs to be synchronized, and, if so, with whom
        $partner = self::get_partner($attempt);

        //if synchronization isn't required, return
        if($partner === false || $attempt->is_preview())
            return;

        //otherwise, perform the sync
        self::copy_to_new_attempt(self::get_quba($attempt), $attempt->get_quizobj(), $partner, true);
    }

    /**
     * Convenience method to copy an attempt from one user to another.
     */
    public function copy_attempt_to_user(quiz_attempt $attempt, $user_id, $finish=true)
    {
        self::copy_to_new_attempt(self::get_quba($attempt), $attempt->get_quizobj(), $user_id, $finish);
    }

    /**
     * Gets the Question Usage By Activity which represents this quiz attempt. 
     * 
     * @param quiz_attempt $attempt         The attempt in question.
     * @return question_usage_by_activity   The QUBA which represents the given attempt.
     */
    public function get_quba(quiz_attempt $attempt)
    {
        //get the QUBA id for this attempt
        $quba_id = $attempt->get_uniqueid();

        //and return the corresponding QUBA 
        return question_engine::load_questions_usage_by_activity($quba_id);
    }

    public static function get_partner(quiz_attempt $attempt)
    {

        //get all quesiton slots for the quiz
        $slots = $attempt->get_slots();

        //if the quiz has a partner question, it requires sync
        foreach($slots as $slot)
        {
            //get the question attempt for the given slot
            $qa = $attempt->get_question_attempt($slot);

            //and use that to get the question itself
            $question = $qa->get_question();

            //if this is a partner question
            if($question->get_type_name() == "partner")
            {
                //get the partner's user ID
                $partner = $qa->get_last_qt_data('answer', -1);

                //if no partner was selected, do not sync
                if(!array_key_exists('answer', $partner))
                    return false; 
                
                //if the user has selected a partner other than him/herself, return that partner
                if($partner['answer'] >= 0 and $partner['answer'] != $attempt->get_userid())
                    return $partner['answer'];

                //otherwise, return false, as no sync is required
                else
                    return false;
            }

        }

        //if quiz lacks a partner question, it does not require sync
        return false;
    }


    public function copy_to_new_attempt(question_usage_by_activity $source, quiz $quiz, $user_id, $finish = false)
    {
        global $DB;

        //create a new QUBA as the target of the new event
        $dest = question_engine::make_questions_usage_by_activity('mod_quiz', $quiz->get_context());

        //get the raw database row for the quiz
        $quizrow = $quiz->get_quiz();

        //copy the data from the quiz exactly
        $dest->set_preferred_behaviour($quizrow->preferredbehaviour);

        //get the slots for the source QUBA
        $slots = $source->get_slots();

        //copy each of the questions from the source QUBA to the new QUBA
        foreach($slots as $slot)
        {
            //get the question that should occupy the given slot
            $question = $source->get_question($slot);

            //and add it to the new QUBA
            $dest->add_question($question, $source->get_question_max_mark($slot));

            //start a new attempt at the question
            $dest->start_question($slot, $source->get_variant($slot));

            //then, immediately overwrite the user's data with their partner's attempt history
            $dest->replace_loaded_question_attempt_info($slot, $source->get_question_attempt($slot));
        }

        //save the destination QUBA to the database
        question_engine::save_questions_usage_by_activity($dest);

        //finally, create the new attempt from the usage, and commit it to the database
        self::build_attempt_from_usage($dest, $quiz, $user_id, $finish, true);
    }


     
     /**
     * Creates a normal quiz attempt from a (typically paper copy) usage, so the student can view the results of a paper test
     * as though they had taken it on Moodle, including feedback. This also allows one to theoretically allow subsequent attempts
     * at the same quiz on Moodle, using options such as "each attempt buiilds on the last", building on the paper copies.
     *
     * @param question_usage_by_actvitity   $usage      The question_usage_by_activity object which composes the paper copy.
     * @param quiz                          $quiz       A quiz object for which the new attempt should be created.
     * @param int                           $user_id    If provided, the attempt will be owned by the user with the given ID, instead of the current user.
     * @param bool                          $finished   If set, the attempt will finished and committed to the database as soon as it is created; this assumes the QUBA has already been populated with responses.
     * @param bool                          $commit     If set, the attempt will be committed to the database after creation. If $finished is set, the value of $commit will be ignored, and the row will be committed regardless.
     *
     * @return array      Returns the newly created attempt's raw data. (In other words, does not return a quiz_attempt object.)
     */
    public static function build_attempt_from_usage($usage, quiz $quiz, $user_id = null, $finished = false, $commit = false, $attempt_number = null)
    {
        global $DB, $USER;

        //get the current time
        $time_now = time();

        //start a new attempt
        $attempt = new stdClass();
        $attempt->quiz = $quiz->get_quizid();
        $attempt->preview = 0;
        $attempt->timestart = $time_now;
        $attempt->timefinish = 0;
        $attempt->timemodified = $time_now;

        //associate the attempt with the usage
        $attempt->uniqueid = $usage->get_id();

        //and set the attempt's owner, if specified
        if($user_id !== null)
            $attempt->userid = $user_id;
        //otherwise, use the current user
        else
            $attempt->userid = $USER->id;

        //if no attempt number was specified, automatically detect one
        if($attempt_number === null)
        {
            //TODO: possibly change to get_record_sql?

            //determine the maximum attempt value for that user/quiz combo
            $max_attempt = $DB->get_records_sql('SELECT max(qa.attempt) FROM {quiz_attempts}  qa WHERE userid = ? AND quiz = ?', array($user_id, $quiz->get_quizid()));

            //debug message
            debugging('Next attempt was '.print_r($max_attempt, 1));

            //a sort of hackish way to be SQL-neutral: certain SQL's return max, some max("attempt"), but they always return just that single element
            //we'll get it, ignoring its name
            $max_attempt = (array)reset($max_attempt);
            $max_attempt = reset($max_attempt);

            //if no attempts exist, let this be the first attempt
            if($max_attempt == null)
                $attempt_number = 1;

            //otherwise, use the next available attempt number
            else
                $attempt_number = $max_attempt + 1;
        }

        //set the attempt number
        $attempt->attempt = $attempt_number;

        //build the attempt layout 
        $attempt->layout = implode(',', $usage->get_slots()); 

        //if requested, commit the attempt to the database
        if($commit || $finished)
        {
            //and use it to save the usage and attempt
            question_engine::save_questions_usage_by_activity($usage);
            $attempt->id = $DB->insert_record('quiz_attempts', $attempt);
        }

        //if requested, finish the attempt immediately
        if($finished)
        {
            $raw_course = $DB->get_record('course', array('id' => $quiz->get_courseid()));

            //wrap the attempt data in an quiz_attempt object, and ask it to finish
            $attempt_object = new quiz_attempt($attempt, $quiz->get_quiz(), $quiz->get_cm(), $quiz->get_course(), true);
            $attempt_object->finish_attempt($time_now);

            //save the attempt _again_, this compensates for an attemptlib that does not have MDL-31407
            quiz_save_best_grade($attempt_object->get_quiz(), $attempt_object->get_userid());
        }


        //return the attempt object
        return $attempt; 
    }
}

/**
 * Moodle Event Handler for Quiz Submissions
 * Automatically run by Moodle on quiz submission.
 * 
 * @param stdclass $eventdata   Event data, as recieved from events_trigger.
 * @return void
 */
function quiz_attempt_submitted_handler_quizsync($eventdata)
{
    //recreate the submitted attempt from the event data
    $attemptobj = quiz_attempt::create($eventdata->attemptid);

    //then, synchronize the quiz attempt if necessary     
    //we trust handle_sync_if_necessary to avoid synchronization loops
    quiz_synchronization::handle_sync_if_necessary($attemptobj);

    //indicate success
    return true;
}
