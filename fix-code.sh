#!/bin/bash
# Auto-fix script untuk WordPress coding standards

echo "🔧 BlazeCommerce Auto-Fix Tool"
echo "=============================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to show usage
show_usage() {
    echo "Usage: ./fix-code.sh [option] [file/directory]"
    echo ""
    echo "Options:"
    echo "  all       - Fix all PHP files in the project"
    echo "  file      - Fix a specific file"
    echo "  dir       - Fix all files in a directory"
    echo "  check     - Check for issues without fixing"
    echo "  help      - Show this help message"
    echo ""
    echo "Examples:"
    echo "  ./fix-code.sh all"
    echo "  ./fix-code.sh file app/Extensions/WooDiscountRules.php"
    echo "  ./fix-code.sh dir app/Extensions/"
    echo "  ./fix-code.sh check"
}

# Function to run phpcbf
run_fix() {
    local target="$1"
    echo -e "${YELLOW}Running auto-fix on: $target${NC}"
    
    if composer run cs:fix-file "$target" 2>/dev/null; then
        echo -e "${GREEN}✓ Auto-fix completed successfully!${NC}"
    else
        echo -e "${RED}✗ Auto-fix encountered some issues${NC}"
        echo "Some issues may need manual fixing"
    fi
}

# Function to check code
run_check() {
    local target="$1"
    echo -e "${YELLOW}Checking code standards for: $target${NC}"
    
    if composer run cs:check "$target" 2>/dev/null; then
        echo -e "${GREEN}✓ Code standards check passed!${NC}"
    else
        echo -e "${RED}✗ Code standards issues found${NC}"
        echo "Run with 'fix' option to auto-fix issues"
    fi
}

# Main logic
case "$1" in
    "all")
        echo -e "${YELLOW}Auto-fixing all PHP files...${NC}"
        composer run cs:fix-all
        echo -e "${GREEN}✓ All files processed!${NC}"
        ;;
    "file")
        if [ -z "$2" ]; then
            echo -e "${RED}Error: Please specify a file${NC}"
            show_usage
            exit 1
        fi
        run_fix "$2"
        ;;
    "dir")
        if [ -z "$2" ]; then
            echo -e "${RED}Error: Please specify a directory${NC}"
            show_usage
            exit 1
        fi
        run_fix "$2"
        ;;
    "check")
        if [ -z "$2" ]; then
            composer run cs:check
        else
            run_check "$2"
        fi
        ;;
    "help"|"--help"|"-h")
        show_usage
        ;;
    *)
        echo -e "${RED}Error: Unknown option '$1'${NC}"
        echo ""
        show_usage
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}Done! 🎉${NC}"
