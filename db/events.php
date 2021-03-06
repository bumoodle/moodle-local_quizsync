<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 
/**
 * Quiz Synchronization local plugin. 
 * Adds support for synchronization using the Partner question type, or similar.
 *
 * @package   local_quizsync
 * @copyright 2012 Binghamton University
 * @author    Kyle J. Temkin <ktemkin@binghamton.edu>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



$handlers =
array
    (
        'quiz_attempt_submitted' => 
            array
            (
                //specify the file and function which will handle synchronizations
                'handlerfile' => '/local/quizsync/synclib.php',
                'handlerfunction' => 'quiz_attempt_submitted_handler_quizsync',

                //and specify that synchronization will happen as soon as the quiz is submitted
                //(rather than waiting for the next cron tick)
                'schedule' => 'instant'
            )
    );
