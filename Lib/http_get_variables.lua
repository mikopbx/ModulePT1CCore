-- Copyright © MIKO LLC - All Rights Reserved
-- Unauthorized copying of this file, via any medium is strictly prohibited
-- Proprietary and confidential
-- Written by Alexey Portnov, 3 2019


-- curl -u adm:123 'http://172.16.156.223/pbxcore/api/miko_ajam/getvar?channel=SIP/201-00000009&variables=CHANNEL(linkedid),EXTEN'
-- curl -u adm:123 'http://172.16.156.223/pbxcore/api/miko_ajam/getvar?event=Ping'
function string:split( inSplitPattern, outResults )
    if not outResults then
        outResults = { }
    end
    local theStart = 1
    local theSplitStart, theSplitEnd = string.find( self, inSplitPattern, theStart )
    while theSplitStart do
        table.insert( outResults, string.sub( self, theStart, theSplitStart-1 ) )
        theStart = theSplitEnd + 1
        theSplitStart, theSplitEnd = string.find( self, inSplitPattern, theStart )
    end
    table.insert( outResults, string.sub( self, theStart ) )
    return outResults
end

-- see if the file exists
function file_exists(file)
    local f = io.open(file, "rb")
    if f then f:close() end
    return f ~= nil
end

-- get all lines from a file, returns an empty
-- list/table if the file does not exist
function lines_from(file)
    if not file_exists(file) then return {} end
    local lines = {}
    for line in io.lines(file) do
        lines[#lines + 1] = line
    end
    return lines
end

local lines = lines_from('/var/etc/http_auth')
local headers = ngx.req.get_headers()
local authorization = ngx.var.pt1c_authorization or ngx.var.http_authorization
    or headers["Authorization"] or headers["authorization"]
if type(authorization) ~= "string" then
    local raw_headers = ngx.req.raw_header()
    authorization = string.match(raw_headers or "", "\r\n[Aa][Uu][Tt][Hh][Oo][Rr][Ii][Zz][Aa][Tt][Ii][Oo][Nn]:[ \t]*([^\r\n]+)")
end
local encoded_auth = type(authorization) == "string" and string.match(authorization, "^[Bb][Aa][Ss][Ii][Cc]%s+(.+)$") or nil
local decoded_auth = encoded_auth and ngx.decode_base64(encoded_auth) or nil

-- Проверка авторизации
if type(lines[1]) ~= "string" or decoded_auth ~= lines[1] then
    ngx.log(ngx.WARN)
    ngx.status = ngx.HTTP_FORBIDDEN
    ngx.say('The user isn\'t authenticated.')
    ngx.exit(ngx.HTTP_FORBIDDEN)
end

local args = ngx.req.get_uri_args()
if args["event"] == 'Ping' then
    -- На запрос пинга всегда отвечаем TRUE.
    ngx.say('true')
    ngx.exit(ngx.HTTP_OK)
end

local channel       = args["channel"];
local str_variables = args["variables"];

local function invalid_ami_value(value, max_length, pattern)
    return type(value) ~= "string"
        or #value == 0
        or #value > max_length
        or string.find(value, "[\r\n%z]") ~= nil
        or string.match(value, pattern) == nil
end

local _, variable_count = string.gsub(str_variables or "", ",", "")
if invalid_ami_value(channel, 255, "^[%w_@/%.:%+%-]+$")
    or invalid_ami_value(str_variables, 1024, "^[%w_(),%./:@%+%-]+$")
    or variable_count >= 32 then
    ngx.status = ngx.HTTP_BAD_REQUEST
    ngx.say('Invalid channel or variables.')
    ngx.exit(ngx.HTTP_BAD_REQUEST)
end

-- TODO -- Прорим кэш данные.
local asterisk_vars = ngx.shared.asterisk_vars
if asterisk_vars ~= nill then
    local cash_data     = asterisk_vars:get(channel..str_variables)
    if cash_data ~= nill then
        ngx.say(cash_data)
        ngx.exit(ngx.HTTP_OK)
    end
end

-- Будем пытаться соединиться с AMI.
local sock = ngx.socket.tcp()
sock:settimeout(500)
local ok = sock:connect("127.0.0.1", 5038)
if not ok then
    --  Не вышло подключиться к AMI.
    ngx.say('New Structure("Result,Msg", false, "AMI connect failed...")')
    sock:close()
    ngx.exit(ngx.HTTP_OK)
end

local ami_secret_lines = lines_from('/var/etc/pt1ccore_ami_secret')
local ami_secret = ami_secret_lines[1]
if type(ami_secret) ~= "string" or string.match(ami_secret, "^[a-f0-9]+$") == nil or #ami_secret ~= 48 then
    ngx.say('New Structure("Result,Msg", false, "AMI credentials unavailable...")')
    sock:close()
    ngx.exit(ngx.HTTP_INTERNAL_SERVER_ERROR)
end

sock:send("Action: Login\r\nUsername: pt1ccore_getvar\r\nSecret: "..ami_secret.."\r\nEvents: off\r\n\r\n")

local reader = sock:receiveuntil("\r\n\r\n")
local data   = reader()

if not data then
    -- Авторизация. Не вышло получить ответ от AMI.
    ngx.say('New Structure("Result,Msg", false, "AMI auth failed...")')
    sock:close()
    ngx.exit(ngx.HTTP_OK)
end

-- Получение переменных канала.
local result_part1 = 'Result'
local result_part2 = 'true'
local variables = str_variables:split(",");

for key,var_name in ipairs(variables) do
    -- Готовим и отправляем команду.
    local command = "Action: GetVar\r\nChannel: "..channel.."\r\nVariable: "..var_name.."\r\n\r\n";
    sock:send(command)

    var_name = string.gsub(var_name, "%(", "_")
    var_name = string.gsub(var_name, "%)", "_")

    local var_value = "";
    -- Чтение результата.
    local data = reader()
    local data_table = data:split("\r\n")
    for key,pair_key_val in ipairs(data_table) do
        local key_val_table = pair_key_val:split(": ")
        if key_val_table[1] == 'Value' then
            var_value    = key_val_table[2];
        end
    end

    result_part1 = ""..result_part1..",p_"..var_name;
    result_part2 = ""..result_part2..", \""..var_value.."\"";
end

bytes = sock:send("Action: Logoff\r\n\r\n")
sock:receiveuntil("\r\n\r\n")
sock:close()

local result = 'New Structure("'..result_part1..'", '..result_part2..')';
ngx.say(result)

-- TODO -- Сохраняем кэш.
asterisk_vars:set(""..channel..str_variables, result, 0.5)
asterisk_vars:flush_expired()
ngx.exit(ngx.HTTP_OK)
