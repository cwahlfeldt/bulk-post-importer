#!/bin/bash

# WordPress Plugin SVN Release Script
# Usage: ./release.sh [version] [--dry-run]

set -e  # Exit on any error

# Configuration
PLUGIN_SLUG="bulk-post-importer"
SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}"
SVN_USERNAME="wafflewolf"
SVN_PASSWORD="${WP_SVN_PASSWORD}"  # Read from environment variable
CURRENT_DIR="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SVN_DIR=""  # Will be set to temp directory

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to validate SVN credentials
validate_svn_credentials() {
    if [ -z "$SVN_PASSWORD" ]; then
        print_error "SVN password not found in environment variable WP_SVN_PASSWORD"
        print_status "Please set your SVN password:"
        print_status "export WP_SVN_PASSWORD='your-password'"
        print_status "Or add it to your ~/.bashrc or ~/.zshrc file"
        exit 1
    fi
    
    print_status "SVN credentials configured for user: $SVN_USERNAME"
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [version] [--dry-run]"
    echo ""
    echo "Environment Variables:"
    echo "  WP_SVN_PASSWORD    Your WordPress.org SVN password (required)"
    echo ""
    echo "Options:"
    echo "  version     Version number (e.g., 1.0.0, 1.2.3)"
    echo "  --dry-run   Show what would be done without actually doing it"
    echo ""
    echo "Examples:"
    echo "  export WP_SVN_PASSWORD='your-password'"
    echo "  $0 1.0.0"
    echo "  $0 1.2.3 --dry-run"
}

# Function to validate version format
validate_version() {
    if [[ ! $1 =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        print_error "Invalid version format. Use semantic versioning (e.g., 1.0.0, 1.2.3)"
        exit 1
    fi
}

# Function to create temporary SVN directory
create_temp_svn_directory() {
    print_status "Creating temporary SVN directory..."
    SVN_DIR=$(mktemp -d -t "${PLUGIN_SLUG}-svn-XXXXXX")
    print_status "Using temporary directory: $SVN_DIR"
    
    print_status "Checking out SVN repository..."
    svn co "$SVN_URL" "$SVN_DIR" --username "$SVN_USERNAME" --password "$SVN_PASSWORD" --non-interactive
    
    if [ $? -ne 0 ]; then
        print_error "Failed to checkout SVN repository"
        print_error "Please check your SVN credentials and network connection"
        cleanup_temp_directory
        exit 1
    fi
}

# Function to cleanup temporary directory
cleanup_temp_directory() {
    if [ -n "$SVN_DIR" ] && [ -d "$SVN_DIR" ]; then
        print_status "Cleaning up temporary directory: $SVN_DIR"
        rm -rf "$SVN_DIR"
    fi
}

# Function to setup cleanup trap
setup_cleanup_trap() {
    trap cleanup_temp_directory EXIT INT TERM
}

# Function to check if version already exists
check_version_exists() {
    local version=$1
    if [ -d "${SVN_DIR}/tags/${version}" ]; then
        print_error "Version ${version} already exists in SVN"
        cleanup_temp_directory
        exit 1
    fi
}

# Function to update version in plugin file
update_plugin_version() {
    local version=$1
    local plugin_file="${SCRIPT_DIR}/bulk-post-importer.php"
    
    if [ ! -f "$plugin_file" ]; then
        print_error "Plugin file not found: $plugin_file"
        exit 1
    fi
    
    # Update version in plugin header
    sed -i.bak "s/Version: .*/Version: $version/" "$plugin_file"
    
    # Update version constant if it exists
    sed -i.bak "s/define.*BULKPOSTIMPORTER_VERSION.*$/define( 'BULKPOSTIMPORTER_VERSION', '$version' );/" "$plugin_file"
    
    print_success "Updated version to $version in plugin file"
}

# Function to update stable tag in readme.txt
update_readme_stable_tag() {
    local version=$1
    local readme_file="${SCRIPT_DIR}/readme.txt"
    
    if [ ! -f "$readme_file" ]; then
        print_error "readme.txt not found: $readme_file"
        exit 1
    fi
    
    sed -i.bak "s/Stable tag: .*/Stable tag: $version/" "$readme_file"
    print_success "Updated stable tag to $version in readme.txt"
}

# Function to copy files to SVN trunk
copy_files_to_svn() {
    print_status "Copying files to SVN trunk..."
    
    # Clean trunk directory
    rm -rf "${SVN_DIR}/trunk/"*
    
    # Copy main plugin file
    cp "${SCRIPT_DIR}/bulk-post-importer.php" "${SVN_DIR}/trunk/"
    
    # Copy includes directory
    cp -r "${SCRIPT_DIR}/includes" "${SVN_DIR}/trunk/"
    
    # Copy languages directory
    cp -r "${SCRIPT_DIR}/languages" "${SVN_DIR}/trunk/"
    
    # Copy readme.txt
    cp "${SCRIPT_DIR}/readme.txt" "${SVN_DIR}/trunk/"
    
    # Copy assets directory (rename to avoid conflict with SVN assets)
    mkdir -p "${SVN_DIR}/trunk/public"
    cp -r "${SCRIPT_DIR}/assets/"* "${SVN_DIR}/trunk/public/"
    
    print_success "Files copied to SVN trunk"
}

# Function to handle SVN operations
handle_svn_operations() {
    local version=$1
    local dry_run=$2
    
    cd "$SVN_DIR"
    
    # Add any new files
    svn add --force trunk/*
    
    # Remove any deleted files
    svn status | grep '^!' | awk '{print $2}' | xargs -r svn remove
    
    if [ "$dry_run" = true ]; then
        print_status "DRY RUN: Would commit changes to trunk"
        svn status
        print_status "DRY RUN: Would create tag $version"
        print_status "DRY RUN: Would commit tag $version"
    else
        # Commit any assets changes first
        if [ -d "${SVN_DIR}/assets" ] && [ "$(ls -A "${SVN_DIR}/assets")" ]; then
            print_status "Committing WordPress.org assets..."
            svn ci -m "Updating WordPress.org assets" --username "$SVN_USERNAME" --password "$SVN_PASSWORD" --non-interactive || true
        fi
        
        # Commit changes to trunk
        print_status "Committing changes to trunk..."
        svn ci -m "Updating trunk for version $version" --username "$SVN_USERNAME" --password "$SVN_PASSWORD" --non-interactive
        
        # Create tag
        print_status "Creating tag $version..."
        svn cp trunk "tags/$version"
        
        # Commit tag
        print_status "Committing tag $version..."
        svn ci -m "Tagging version $version" --username "$SVN_USERNAME" --password "$SVN_PASSWORD" --non-interactive
        
        print_success "Successfully released version $version"
        print_success "Temporary directory will be cleaned up automatically"
    fi
    
    cd "$CURRENT_DIR"
}

# Function to create WordPress.org assets
create_wordpress_assets() {
    print_status "Checking for WordPress.org assets..."
    
    # Check if assets directory exists in SVN root
    if [ ! -d "${SVN_DIR}/assets" ]; then
        mkdir -p "${SVN_DIR}/assets"
        print_warning "Created assets directory in temporary SVN checkout"
    fi
    
    # Check if we have assets in our source directory to copy
    local source_assets_dir="${SCRIPT_DIR}/wordpress-assets"
    if [ -d "$source_assets_dir" ]; then
        print_status "Copying WordPress.org assets from $source_assets_dir..."
        cp -r "$source_assets_dir/"* "${SVN_DIR}/assets/"
        
        # Add new assets to SVN
        cd "$SVN_DIR"
        svn add --force assets/*
        cd "$CURRENT_DIR"
        
        print_success "WordPress.org assets copied"
    else
        print_warning "No WordPress.org assets found in $source_assets_dir"
        print_status "Create a 'wordpress-assets' directory in your plugin root with:"
        echo "  - icon-128x128.png (plugin icon)"
        echo "  - icon-256x256.png (plugin icon)"
        echo "  - banner-772x250.png (plugin banner)"
        echo "  - banner-1544x500.png (plugin banner)"
        echo "  - screenshot-1.png (first screenshot)"
        echo "  - screenshot-2.png (second screenshot)"
        echo "  - etc..."
    fi
}

# Function to run pre-release checks
run_pre_release_checks() {
    print_status "Running pre-release checks..."
    
    # Check if we're in a git repository
    if [ ! -d "${SCRIPT_DIR}/.git" ]; then
        print_warning "Not in a git repository"
    else
        # Check if working directory is clean
        if [ -n "$(git status --porcelain)" ]; then
            print_warning "Git working directory is not clean"
            git status --short
        fi
        
        # Check current branch
        local branch=$(git rev-parse --abbrev-ref HEAD)
        print_status "Current git branch: $branch"
    fi
    
    # Check if required files exist
    local required_files=("bulk-post-importer.php" "readme.txt" "includes" "languages")
    for file in "${required_files[@]}"; do
        if [ ! -e "${SCRIPT_DIR}/$file" ]; then
            print_error "Required file/directory not found: $file"
            exit 1
        fi
    done
    
    print_success "Pre-release checks passed"
}

# Main script execution
main() {
    local version=""
    local dry_run=false
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --dry-run)
                dry_run=true
                shift
                ;;
            --help|-h)
                show_usage
                exit 0
                ;;
            *)
                if [ -z "$version" ]; then
                    version=$1
                else
                    print_error "Unknown option: $1"
                    show_usage
                    exit 1
                fi
                shift
                ;;
        esac
    done
    
    # Check if version is provided
    if [ -z "$version" ]; then
        print_error "Version number is required"
        show_usage
        exit 1
    fi
    
    # Validate version format
    validate_version "$version"
    
    # Validate SVN credentials
    validate_svn_credentials
    
    print_status "Starting release process for version $version"
    if [ "$dry_run" = true ]; then
        print_warning "DRY RUN MODE - No actual changes will be made"
    fi
    
    # Setup cleanup trap
    setup_cleanup_trap
    
    # Run pre-release checks
    run_pre_release_checks
    
    # Create temporary SVN directory
    create_temp_svn_directory
    
    # Check if version already exists
    check_version_exists "$version"
    
    if [ "$dry_run" = false ]; then
        # Update version numbers
        update_plugin_version "$version"
        update_readme_stable_tag "$version"
    fi
    
    # Copy files to SVN
    copy_files_to_svn
    
    # Handle SVN operations
    handle_svn_operations "$version" "$dry_run"
    
    # Create WordPress.org assets directory if needed
    create_wordpress_assets
    
    if [ "$dry_run" = false ]; then
        print_success "Release $version completed successfully!"
        print_status "Plugin will be available at: https://wordpress.org/plugins/${PLUGIN_SLUG}/"
        print_status "To add WordPress.org assets (icons, banners, screenshots):"
        print_status "Create a 'wordpress-assets' directory in your plugin root"
    else
        print_success "Dry run completed - no changes were made"
    fi
    
    # Cleanup happens automatically via trap
}

# Run main function with all arguments
main "$@"