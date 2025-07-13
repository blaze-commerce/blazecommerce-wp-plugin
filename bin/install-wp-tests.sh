#!/usr/bin/env bash

# WordPress Test Environment Setup Script
# Enhanced with comprehensive dependency checking and error handling

# Enable strict error handling
set -e

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to check dependencies
check_dependencies() {
    echo "CHECKING: Verifying required dependencies..."

    local missing_deps=()

    # Check for SVN (Subversion) - Critical dependency
    if ! command_exists svn; then
        missing_deps+=("subversion")
        echo "ERROR: SVN (Subversion) is required but not installed"
    else
        echo "SUCCESS: SVN found - $(svn --version --quiet)"
    fi

    # Check for HTTP client (curl or wget)
    if ! command_exists curl && ! command_exists wget; then
        missing_deps+=("curl or wget")
        echo "ERROR: Either curl or wget is required but neither is installed"
    else
        if command_exists curl; then
            echo "SUCCESS: curl found - $(curl --version | head -1)"
        else
            echo "SUCCESS: wget found - $(wget --version | head -1)"
        fi
    fi

    # Check for unzip
    if ! command_exists unzip; then
        missing_deps+=("unzip")
        echo "ERROR: unzip is required but not installed"
    else
        echo "SUCCESS: unzip found"
    fi

    # Check for tar
    if ! command_exists tar; then
        missing_deps+=("tar")
        echo "ERROR: tar is required but not installed"
    else
        echo "SUCCESS: tar found"
    fi

    # Check for MySQL client tools
    if ! command_exists mysql; then
        missing_deps+=("mysql-client")
        echo "ERROR: mysql client is required but not installed"
    else
        echo "SUCCESS: mysql client found"
    fi

    if ! command_exists mysqladmin; then
        missing_deps+=("mysql-client (mysqladmin)")
        echo "ERROR: mysqladmin is required but not installed"
    else
        echo "SUCCESS: mysqladmin found"
    fi

    # Check for sed and grep (should be available on most systems)
    if ! command_exists sed; then
        missing_deps+=("sed")
        echo "ERROR: sed is required but not installed"
    fi

    if ! command_exists grep; then
        missing_deps+=("grep")
        echo "ERROR: grep is required but not installed"
    fi

    # If any dependencies are missing, provide installation instructions and exit
    if [ ${#missing_deps[@]} -ne 0 ]; then
        echo ""
        echo "DEPENDENCY ERROR: The following required dependencies are missing:"
        for dep in "${missing_deps[@]}"; do
            echo "  - $dep"
        done
        echo ""
        echo "Installation instructions:"
        echo "  Ubuntu/Debian: sudo apt-get install subversion curl unzip mysql-client"
        echo "  CentOS/RHEL:   sudo yum install subversion curl unzip mysql"
        echo "  macOS:         brew install subversion mysql-client"
        echo ""
        exit 1
    fi

    echo "SUCCESS: All dependencies verified successfully"
}

# Check script arguments
if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

# Check dependencies before proceeding
check_dependencies

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress/}

download() {
    local url="$1"
    local output="$2"

    echo "DOWNLOADING: $url"

    if command_exists curl; then
        if ! curl -s -f "$url" > "$output"; then
            echo "ERROR: Failed to download $url using curl"
            return 1
        fi
    elif command_exists wget; then
        if ! wget -nv -O "$output" "$url"; then
            echo "ERROR: Failed to download $url using wget"
            return 1
        fi
    else
        echo "ERROR: Neither curl nor wget is available for downloading"
        return 1
    fi

    # Verify the download was successful and file is not empty
    if [ ! -f "$output" ] || [ ! -s "$output" ]; then
        echo "ERROR: Download failed or resulted in empty file: $output"
        return 1
    fi

    echo "SUCCESS: Downloaded $url to $output"
    return 0
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
	WP_BRANCH=${WP_VERSION%\-*}
	WP_TESTS_TAG="branches/$WP_BRANCH"

elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
		# version x.x.0 means the first release of the major version, so strip off the .0 and download version x.x
		WP_TESTS_TAG="tags/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	# http serves a single offer, whereas https serves multiple. we only want one
	download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	grep '[0-9]+\.[0-9]+(\.[0-9]+)?' /tmp/wp-latest.json
	LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
	if [[ -z "$LATEST_VERSION" ]]; then
		echo "Latest WordPress version could not be found"
		exit 1
	fi
	WP_TESTS_TAG="tags/$LATEST_VERSION"
fi
set -ex

install_wp() {

	if [ -d $WP_CORE_DIR ]; then
		return;
	fi

	mkdir -p $WP_CORE_DIR

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		mkdir -p $TMPDIR/wordpress-nightly
		download https://wordpress.org/nightly-builds/wordpress-latest.zip  $TMPDIR/wordpress-nightly/wordpress-nightly.zip
		unzip -q $TMPDIR/wordpress-nightly/wordpress-nightly.zip -d $TMPDIR/wordpress-nightly/
		mv $TMPDIR/wordpress-nightly/wordpress/* $WP_CORE_DIR
	else
		if [ $WP_VERSION == 'latest' ]; then
			local ARCHIVE_NAME='latest'
		elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
			# https serves multiple offers, whereas http serves single.
			download https://api.wordpress.org/core/version-check/1.7/ $TMPDIR/wp-latest.json
			if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
				# version x.x.0 means the first release of the major version, so strip off the .0 and download version x.x
				LATEST_VERSION=${WP_VERSION%??}
			else
				# otherwise, use the exact version
				LATEST_VERSION=$WP_VERSION
			fi
			local ARCHIVE_NAME="wordpress-$LATEST_VERSION"
		else
			local ARCHIVE_NAME="wordpress-$WP_VERSION"
		fi

		download https://wordpress.org/${ARCHIVE_NAME}.tar.gz  $TMPDIR/wordpress.tar.gz
		tar --strip-components=1 -zxmf $TMPDIR/wordpress.tar.gz -C $WP_CORE_DIR
	fi

	download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
}

install_test_suite() {
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i.bak'
	else
		local ioption='-i'
	fi

	# set up testing suite if it doesn't yet exist
	if [ ! -d $WP_TESTS_DIR ]; then
		echo "SETUP: Creating WordPress test suite directory..."
		mkdir -p $WP_TESTS_DIR

		echo "DOWNLOADING: WordPress test includes via SVN..."
		if ! svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes; then
			echo "ERROR: Failed to download WordPress test includes from SVN"
			echo "URL: https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/"
			echo "This could be due to:"
			echo "  - Network connectivity issues"
			echo "  - SVN server unavailability"
			echo "  - Invalid WordPress version tag: ${WP_TESTS_TAG}"
			exit 1
		fi

		echo "DOWNLOADING: WordPress test data via SVN..."
		if ! svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data; then
			echo "ERROR: Failed to download WordPress test data from SVN"
			echo "URL: https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/"
			echo "This could be due to:"
			echo "  - Network connectivity issues"
			echo "  - SVN server unavailability"
			echo "  - Invalid WordPress version tag: ${WP_TESTS_TAG}"
			exit 1
		fi

		echo "SUCCESS: WordPress test suite downloaded successfully"
	else
		echo "INFO: WordPress test suite directory already exists, skipping download"
	fi

	if [ ! -f wp-tests-config.php ]; then
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
		# remove all forward slashes in the end
		WP_CORE_DIR=$(echo $WP_CORE_DIR | sed "s:/\+$::")
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
	fi

}

recreate_db() {
	shopt -s nocasematch
	if [[ $1 =~ ^(y|yes)$ ]]
	then
		mysqladmin drop $DB_NAME -f --user="$DB_USER" --password="$DB_PASS"$EXTRA
		create_db
		echo "Recreated the database ($DB_NAME)."
	else
		echo "Leaving the existing database ($DB_NAME) as is."
	fi
	shopt -u nocasematch
}

create_db() {
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_db() {

	if [ ${SKIP_DB_CREATE} = "true" ]; then
		return 0
	fi

	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z $DB_HOSTNAME ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# create database
	if [ $(mysql --user="$DB_USER" --password="$DB_PASS"$EXTRA --execute="SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE schema_name = '$DB_NAME';" | tail -1) != 0 ]
	then
		echo "Reinstalling will delete the existing test database ($DB_NAME)"
		read -p 'Are you sure you want to proceed? [y/N]: ' DELETE_EXISTING_DB
		recreate_db $DELETE_EXISTING_DB
	else
		create_db
	fi
}

install_wp
install_test_suite
install_db
