Quiz Sync Local Plugin for Moodle 2.2+
==================================================

Authored by Kyle Temkin, working for Binghamton University <http://www.binghamton.edu>

Description
---------------

This is a special library which allows Quiz attempts to be copied and "synchronized" between users; and allows raw QUBAs to be converted into quiz attempts. It has a few uses, currently:

* When combined with the Partner question type, it allows students to share a quiz attempt with a partner- like a lab partner.
* Provides the backbone for the Prinable Quizzes modificatio, which allows paper assignments to be scanned (or read using a scan-tron machine) and then automatically graded by moodle.


Installation
-----------------

To install Moodle 2.1+ using git, execute the following commands in the root of your Moodle install:

    git clone git://github.com/ktemkin/moodle-local_quizsync.git local/quizsync
    echo '/local/quizsync' >> .git/info/exclude
    
Or, extract the following zip in your_moodle_root/local/:

    https://github.com/ktemkin/moodle-local_quizsync/zipball/master
