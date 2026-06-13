#!/bin/bash
# Script to rename lowercase classes to PascalCase in FusionDirectory
set -e

BASE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
INCLUDE_DIR="$BASE_DIR/include"

# Mapping: old_class_name => NewClassName
declare -A CLASS_MAP=(
  ["config"]="Config"
  ["userinfo"]="UserInfo"
  ["session"]="Session"
  ["pluglist"]="Pluglist"
  ["acl"]="Acl"
  ["logging"]="Logging"
  ["tests"]="Tests"
  ["baseSelector"]="BaseSelector"
  ["divSelectBox"]="DivSelectBox"
  ["ldapFilter"]="LdapFilter"
  ["ldapFilterLeaf"]="LdapFilterLeaf"
  ["ldapMultiplexer"]="LdapMultiplexer"
  ["ldapSizeLimit"]="LdapSizeLimit"
  ["msgPool"]="MsgPool"
  ["msg_dialog"]="MsgDialog"
  ["objects"]="Objects"
  ["passwordRecovery"]="PasswordRecovery"
  ["template"]="Template"
  ["templateHandling"]="TemplateHandling"
  ["timezone"]="Timezone"
  ["userinfoNoAuth"]="UserInfoNoAuth"
  ["xml"]="Xml"
  ["printAClass"]="PrintAClass"
  ["simplePlugin"]="SimplePlugin"
  ["simpleService"]="SimpleService"
  ["simpleTabs"]="SimpleTabs"
  ["simpleTabs_noSpecial"]="SimpleTabsNoSpecial"
  ["multiPlugin"]="MultiPlugin"
  ["management"]="Management"
  ["managementFilter"]="ManagementFilter"
  ["managementListing"]="ManagementListing"
  ["selectManagement"]="SelectManagement"
  ["templateDialog"]="TemplateDialog"
  ["passwordMethod"]="PasswordMethod"
  ["passwordMethodArgon2"]="PasswordMethodArgon2"
  ["passwordMethodClear"]="PasswordMethodClear"
  ["passwordMethodCrypt"]="PasswordMethodCrypt"
  ["passwordMethodEmpty"]="PasswordMethodEmpty"
  ["passwordMethodMd5"]="PasswordMethodMd5"
  ["passwordMethodSasl"]="PasswordMethodSasl"
  ["passwordMethodSha"]="PasswordMethodSha"
  ["passwordMethodSmd5"]="PasswordMethodSmd5"
  ["passwordMethodSsha"]="PasswordMethodSsha"
  ["passwordMethodSsha512"]="PasswordMethodSsha512"
  ["systemSelect"]="SystemSelect"
  ["mailAddressSelect"]="MailAddressSelect"
  ["phoneSelect"]="PhoneSelect"
  ["phoneSelectDialog"]="PhoneSelectDialog"
)

echo "Phase 1: Renaming class declarations and references..."

# Process all PHP/INC files
find "$INCLUDE_DIR" "$BASE_DIR/setup" "$BASE_DIR/plugins" "$BASE_DIR/html" -name '*.php' -o -name '*.inc' 2>/dev/null | while read -r file; do
  modified=false
  for old in "${!CLASS_MAP[@]}"; do
    new="${CLASS_MAP[$old]}"
    # Only rename if the file contains the old name
    if grep -q "\b${old}\b" "$file" 2>/dev/null; then
      # Replace class declarations: class oldname → class NewName
      sed -i "s/\bclass ${old}\b/class ${new}/g" "$file"
      # Replace extends: extends oldname → extends NewName
      sed -i "s/\bextends ${old}\b/extends ${new}/g" "$file"
      # Replace instanceof: instanceof oldname → instanceof NewName
      sed -i "s/\binstanceof ${old}\b/instanceof ${new}/g" "$file"
      # Replace new: new oldname( → new NewName(
      sed -i "s/\bnew ${old}\b(/new ${new}(/g" "$file"
      # Replace type hints in function params: oldname $var → NewName $var
      sed -i "s/\b${old} \\\$/\\${new} \\\$/g" "$file"
      # Replace static calls: oldname:: → NewName::
      sed -i "s/\b${old}::/${new}::/g" "$file"
      # Replace string references in arrays/plInfo: 'oldname' => '...'
      sed -i "s/'${old}'/'${new}'/g" "$file"
      # Replace in plInfo plProvidedAcls references
      sed -i "s/\"${old}\"/\"${new}\"/g" "$file"
    fi
  done
done

echo "Phase 2: Renaming files..."

# File renames (old filename pattern → new filename)
declare -A FILE_MAP=(
  ["class_config.inc"]="Config.php"
  ["class_userinfo.inc"]="UserInfo.php"
  ["class_session.inc"]="Session.php"
  ["class_pluglist.inc"]="Pluglist.php"
  ["class_acl.inc"]="Acl.php"
  ["class_logging.inc"]="Logging.php"
  ["class_tests.inc"]="Tests.php"
  ["class_baseSelector.inc"]="BaseSelector.php"
  ["class_divSelectBox.inc"]="DivSelectBox.php"
  ["class_ldapFilter.inc"]="LdapFilter.php"
  ["class_ldapMultiplexer.inc"]="LdapMultiplexer.php"
  ["class_ldapSizeLimit.inc"]="LdapSizeLimit.php"
  ["class_msgPool.inc"]="MsgPool.php"
  ["class_msg_dialog.inc"]="MsgDialog.php"
  ["class_objects.inc"]="Objects.php"
  ["class_passwordRecovery.inc"]="PasswordRecovery.php"
  ["class_template.inc"]="Template.php"
  ["class_templateHandling.inc"]="TemplateHandling.php"
  ["class_timezone.inc"]="Timezone.php"
  ["class_userinfoNoAuth.inc"]="UserInfoNoAuth.php"
  ["class_xml.inc"]="Xml.php"
  ["class_ldap.inc"]="Ldap.php"
  ["class_ldapGeneralizedTime.inc"]="LdapGeneralizedTime.php"
  ["class_ldapSizeLimit.inc"]="LdapSizeLimit.php"
  ["class_CopyPasteHandler.inc"]="CopyPasteHandler.php"
  ["class_CSRFProtection.inc"]="CSRFProtection.php"
  ["class_IconTheme.inc"]="IconTheme.php"
  ["class_Language.inc"]="Language.php"
  ["class_heartbeat.inc"]="Heartbeat.php"
  ["class_simplePlugin.inc"]="SimplePlugin.php"
  ["class_simpleService.inc"]="SimpleService.php"
  ["class_simpleTabs.inc"]="SimpleTabs.php"
  ["class_multiPlugin.inc"]="MultiPlugin.php"
  ["class_multiPluginSection.inc"]="MultiPluginSection.php"
  ["class_management.inc"]="Management.php"
  ["class_managementFilter.inc"]="ManagementFilter.php"
  ["class_managementListing.inc"]="ManagementListing.php"
  ["class_selectManagement.inc"]="SelectManagement.php"
  ["class_templateDialog.inc"]="TemplateDialog.php"
  ["class_passwordMethod.inc"]="PasswordMethod.php"
  ["class_passwordMethodArgon2.inc"]="PasswordMethodArgon2.php"
  ["class_passwordMethodClear.inc"]="PasswordMethodClear.php"
  ["class_passwordMethodCrypt.inc"]="PasswordMethodCrypt.php"
  ["class_passwordMethodEmpty.inc"]="PasswordMethodEmpty.php"
  ["class_passwordMethodMd5.inc"]="PasswordMethodMd5.php"
  ["class_passwordMethodSasl.inc"]="PasswordMethodSasl.php"
  ["class_passwordMethodSha.inc"]="PasswordMethodSha.php"
  ["class_passwordMethodSmd5.inc"]="PasswordMethodSmd5.php"
  ["class_passwordMethodSsha.inc"]="PasswordMethodSsha.php"
  ["class_passwordMethodSsha512.inc"]="PasswordMethodSsha512.php"
  ["class_systemSelect.inc"]="SystemSelect.php"
  ["class_DialogAttribute.inc"]="DialogAttribute.php"
  ["class_DialogOrderedArrayAttribute.inc"]="DialogOrderedArrayAttribute.php"
  ["class_GenericDialog.inc"]="GenericDialog.php"
  ["class_Attribute.inc"]="Attribute.php"
  ["interface_SimpleTab.inc"]="SimpleTab.php"
  ["interface_FusionDirectoryDialog.inc"]="FusionDirectoryDialog.php"
  ["interface_UserTabLockingAction.inc"]="UserTabLockingAction.php"
)

for old_name in "${!FILE_MAP[@]}"; do
  new_name="${FILE_MAP[$old_name]}"
  # Find and rename files
  find "$INCLUDE_DIR" -name "$old_name" 2>/dev/null | while read -r file; do
    dir="$(dirname "$file")"
    if [ -f "$file" ] && [ ! -f "$dir/$new_name" ]; then
      echo "  Renaming: $old_name → $new_name"
      mv "$file" "$dir/$new_name"
    fi
  done
done

echo "Done! Run 'make lint' and 'make test' to verify."
