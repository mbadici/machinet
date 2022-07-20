#Generate PHP wrapper
include_directories(../)

set(KOLAB_SWIG_PHP_SOURCE_FILE php_kolabformat_wrapper.cpp)
add_custom_command(OUTPUT ${CMAKE_CURRENT_BINARY_DIR}/${KOLAB_SWIG_PHP_SOURCE_FILE} ${CMAKE_CURRENT_BINARY_DIR}/kolabformat.php
        COMMAND ${SWIG} -v -c++ -php7 -o ${CMAKE_CURRENT_BINARY_DIR}/${KOLAB_SWIG_PHP_SOURCE_FILE}  ../kolabformat.i
        COMMENT "Generating php bindings"
        WORKING_DIRECTORY ${CMAKE_CURRENT_SOURCE_DIR}
        DEPENDS ../kolabformat.i kolabxml
        VERBATIM
    )

set_source_files_properties(${KOLAB_SWIG_PHP_SOURCE_FILE} PROPERTIES GENERATED 1)
add_custom_target(generate_php_bindings ALL DEPENDS ${KOLAB_SWIG_PHP_SOURCE_FILE})


#Compile PHP Bindings
# Since there is no php library we can't compile with -Wl,--no-undefined
if (APPLE)
    set( CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} -flat_namespace -undefined suppress" )
endif()
set( CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} -Wno-unused-variable -Wno-unused-label -Wno-maybe-uninitialized -Wno-unused-function" )

# Try to find PHP5
find_path(PHP_INCLUDE_DIR NAMES main/php.h PATH_SUFFIXES php php5)
find_program(PHP_EXECUTABLE NAMES php)

# Libkolabxml needs PHP >= 5.3
set(PHP_MIN_VERSION 50300)

# Find where to install the extension files if it's not defined
#if(NOT DEFINED PHP_INSTALL_DIR)
  find_program(PHP_CONFIG_EXECUTABLE NAMES php-config)
  if(PHP_CONFIG_EXECUTABLE)
    execute_process(COMMAND ${PHP_CONFIG_EXECUTABLE} --extension-dir
                    OUTPUT_VARIABLE _php_extensions_dir

    
    )



 execute_process(COMMAND ${PHP_CONFIG_EXECUTABLE} --vernum
                    OUTPUT_VARIABLE PHP_VERSION_ID

	)
  message("version a: ${PHP_VERSION_ID}")
 
 message("version a: ${PHP_CONFIG_EXECUTABLE}")


execute_process(COMMAND ${PHP_CONFIG_EXECUTABLE} --include-dir
                    OUTPUT_VARIABLE PHP_INCLUDE_DIR

        )

  message("versionaa: ${PHP_INCLUDE_DIR}")


string(REGEX REPLACE "\n" "" PHP_INCLUDE_DIR "${PHP_INCLUDE_DIR}")
string(REGEX REPLACE "\n" "" PHP_VERSION_ID "${PHP_VERSION_ID}")


    string(REGEX REPLACE "\n" "" _php_extensions_dir "${_php_extensions_dir}")
    set(PHP_INSTALL_DIR ${_php_extensions_dir} CACHE STRING "Install directory for PHP bindings.")
  else()
    set(PHP_INSTALL_DIR ${LIB_INSTALL_DIR}/extensions)
  endif()
#endif()

if(PHP_INCLUDE_DIR AND PHP_EXECUTABLE)
  file(READ ${PHP_INCLUDE_DIR}/main/php_version.h PHP_VERSION_CONTENT)
  string(REGEX MATCH "#define PHP_VERSION_ID[ ]*[0-9]*\n" _PHP_VERSION_ID_MATCH ${PHP_VERSION_CONTENT})
  if(_PHP_VERSION_ID_MATCH)
    string(REGEX REPLACE "#define PHP_VERSION_ID[ ]*([0-9]*)\n" "\\1" PHP_VERSION_ID ${_PHP_VERSION_ID_MATCH})
  endif()

  # Include the needed PHP5 subdirs
  set(PHP_INCLUDE_DIRS ${PHP_INCLUDE_DIR} ${PHP_INCLUDE_DIR}/main ${PHP_INCLUDE_DIR}/TSRM ${PHP_INCLUDE_DIR}/Zend )
endif()

if(NOT PHP_VERSION_ID VERSION_LESS ${PHP_MIN_VERSION})
  include_directories(${PHP_INCLUDE_DIRS})
  add_library(phpbindings SHARED ${KOLAB_SWIG_PHP_SOURCE_FILE})
  target_link_libraries(phpbindings kolabxml)
  set_target_properties(phpbindings PROPERTIES OUTPUT_NAME "kolabformat")
  set_target_properties(phpbindings PROPERTIES PREFIX "")

  configure_file(test.php ${CMAKE_CURRENT_BINARY_DIR} COPYONLY)

  install(TARGETS phpbindings LIBRARY DESTINATION ${PHP_INSTALL_DIR})

  install( FILES
          ${CMAKE_CURRENT_BINARY_DIR}/kolabformat.php
          DESTINATION ${PHP_INSTALL_DIR}
      )

else()
 message("include: ${PHP_INCLUDE_DIR}")

  message("version: ${PHP_VERSION_ID}")
  message(WARNING "not building php bindings because php was not found")
endif()
