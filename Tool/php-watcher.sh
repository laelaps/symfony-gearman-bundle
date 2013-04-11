#!/usr/bin/env bash

FILE_PATTERN=$1;
LOG_DIRECTORY=${2%/};
RUN_WORKER_COMMAND=$3;

if [ ! -w "$LOG_DIRECTORY" ]; then
    echo "LOG DIRECTORY '$LOG_DIRECTORY' IS NOT WRITABLE BY '`whoami`'";
    exit 1
fi

for FILE in $FILE_PATTERN; do
    PS_ENTRY="`ps x | grep \"$RUN_WORKER_COMMAND\" | grep $FILE`";
    SHOULD_RUN=false;

    if [ -z "$PS_ENTRY" ]; then
        SHOULD_RUN=true;
    else
        PID="`echo $PS_ENTRY | cut -d ' ' -f 1`"
        PROCMTIME="`date -d \"\`stat /proc/$PID/ | grep Modify | cut -c 9-\`\" \"+%s\"`"

        RUNNING_FILE="`echo $PS_ENTRY | cut -d ' ' -f 8`";
        FILEMTIME="`date -d \"\`stat $FILE | grep Modify | cut -c 9-\`\" \"+%s\"`"

        if [ $FILEMTIME -gt $PROCMTIME ]; then
            # worker needs to be restarted
            kill -SIGTERM $PID;
            SHOULD_RUN=true;
        fi
    fi;

    if ( $SHOULD_RUN ); then
        FILE_UNDERSCORED=${FILE//[^a-Z]/_};

        if [ -n "$FILE_UNDERSCORED" ]; then
            STANDARD_LOG_FILE="$LOG_DIRECTORY/$FILE_UNDERSCORED.log";
            ERROR_LOG_FILE="$LOG_DIRECTORY/$FILE_UNDERSCORED.error.log";

            SHOULD_CONTINUE=false;

            if [ ! -f "$STANDARD_LOG_FILE" ]; then
                touch $STANDARD_LOG_FILE;
            fi

            if [ ! -w "$STANDARD_LOG_FILE" ]; then
                echo "STANDARD LOG FILE '${STANDARD_LOG_FILE}' IS NOT WRITABLE BY '`whoami`'";
                SHOULD_CONTINUE=true;
            fi

            if [ ! -f "$ERROR_LOG_FILE" ]; then
                touch $ERROR_LOG_FILE;
            fi

            if [ ! -w "$ERROR_LOG_FILE" ]; then
                echo "ERROR LOG FILE '${ERROR_LOG_FILE}' IS NOT WRITABLE BY '`whoami`'";
                SHOULD_CONTINUE=true;
            fi

            if ( $SHOULD_CONTINUE ); then
                continue;
            fi

            ( $RUN_WORKER_COMMAND "$FILE" 1> "$STANDARD_LOG_FILE" 2> "$ERROR_LOG_FILE"; ) &
       fi
    fi
done
