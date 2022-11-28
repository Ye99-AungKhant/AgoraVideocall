@extends('layouts.app')

@section('styles')
<style>

#video-container {
    width: 700px;
    height: 500px;
    max-width: 90vw;
    max-height: 50vh;
    margin: 0 auto;
    border: 1px solid #099dfd;
    position: relative;
    box-shadow: 1px 1px 11px #9e9e9e;
    background-color: #fff;
}

#local-video {
    width: 30%;
    height: 30%;
    position: absolute;
    left: 10px;
    bottom: 10px;
    border: 1px solid #fff;
    border-radius: 6px;
    z-index: 2;
    cursor: pointer;
}

#remote-video {
    width: 100%;
    height: 100%;
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    top: 0;
    z-index: 1;
    margin: 0;
    padding: 0;
    cursor: pointer;
}
.hide{
    display: none;
}

.action-btns {
    position: absolute;
    bottom: 20px;
    left: 50%;
    margin-left: -50px;
    z-index: 3;
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
}
</style>
@endsection
@section('content')
    <div class="container">
        <div class="row">
            <div class="col">
                <div class="btn-group" role="group">
                    @foreach ($users as $user)
                        <button type="button" class="btn btn-primary me-2" onclick="placeCall('{{$user->id}}','{{$user->name}}')">{{$user->name}}
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="col">
                <ul id="isOnline">

                </ul>
            </div>
        </div>
    </div>

    <!-- Incoming Call  -->
    <div class="row my-5 hide" id="incoming_call">
        <div class="col-12">
            <p>
                Incoming Call From <strong></strong>
            </p>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-danger" data-dismiss="modal" id="decline_call" onclick="declineCall()">
                    Decline
                </button>
                <button type="button" class="btn btn-success ml-5" onclick="acceptCall()">
                    Accept
                </button>
            </div>
        </div>
    </div>
    <!-- End of Incoming Call  -->

    <section id="video-container" class="hide">
        <div id="local-video"></div>
        <div id="remote-video"></div>

        <div class="action-btns">
            <button type="button" class="btn btn-info" id="muteAudio" onclick="handleAudioToggle()">
                Mute Audio
            </button>
            <button type="button" class="btn btn-primary mx-4" id="muteVideo" onclick="handleVideoToggle()">
                Mute Video
            </button>
            <button type="button" class="btn btn-danger" onclick="endCall()">
                EndCall
            </button>
        </div>
    </section>


@endsection

@push('scripts')

<script>
    const user_isOnline = document.getElementById('isOnline')
    let onlineUsers = []
    let client = null
    let callPlaced = false
    let localStream = null
    let incomingCaller = ""
    let agoraChannel = null
    let incomingCall = false
    let mutedVideo = false
    let mutedAudio = false
    const agora_id = 'e8d6696cc7dc449dbd78ebbd1e15ee13'

    let incoming_call = document.getElementById('incoming_call')
    let video_container = document.getElementById('video-container')
    let muteAudio = document.getElementById('muteAudio')
    let muteVideo = document.getElementById('muteVideo')

    console.log(incoming_call);

    let authuser ="{{ auth()->user()->name }}"
    let authuserId = {{auth()->id()}}
    console.log(authuser)


    Echo.join('agora-videocall')
        .here((users) => {
            onlineUsers = users
            console.log(onlineUsers, 'onlineuser');


            users.forEach((user, index) => {
                const onlineUserIndex = onlineUsers.findIndex(
                (data) => data.id === user.id
            )
            if (onlineUserIndex < 0) {
                let element = document.createElement('li')
                element.innerText =user.name +' is offline'
                user_isOnline.append(element)
            }else{
                let element = document.createElement('li')
                element.innerText =user.name +' is online'
                user_isOnline.append(element)
            }
            })

        })
        .joining((user) =>{
            const joiningUserIndex = onlineUsers.findIndex(
                    (data) => data.id === user.id
                )
                if (joiningUserIndex < 0) {
                    onlineUsers.push(user);
                }
        })
        .leaving((user) =>{
            const leavingUserIndex = onlineUsers.findIndex(
                    (data) => data.id === user.id
                );
                onlineUsers.splice(leavingUserIndex, 1);
        })
        // listen to incomming call
        .listen("MakeAgoraCall", ({ data }) =>{
            if (parseInt(data.userToCall) === parseInt(authuserId)){
                const callerIndex = onlineUsers.findIndex(
                        (user) => user.id === data.from
                    )

                    incomingCaller = onlineUsers[callerIndex]["name"]
                    incomingCall = true

                    if(incomingCall){
                        incoming_call.classList.remove('hide')
                    }
                    // the channel that was sent over to the user being called is what
                    // the receiver will use to join the call when accepting the call.

                    agoraChannel = data.channelName
            }
        })


      async function placeCall(id,call_name){
            try {
                const channelName = `${authuser}_${call_name}`;
                const tokenRes = await generateToken(channelName)

                console.log(tokenRes.data);

                axios.post("/agora/call-user", {
                        user_to_call: id,
                        username: authuser,
                        channel_name: channelName,
                    });
                    initializeAgora()
                    joinRoom(tokenRes.data, channelName)
                    callPlaced = true
                    if(callPlaced){
                        video_container.classList.remove('hide')
                    }
            } catch (error) {
                console.log(error);
            }

        }



       async function joinRoom(token, channel) {
            console.log('leeeeeee',channel);
            client.join(
                token,
                channel,
                authuser,
                (uid) => {
                    console.log("User " + uid + " join channel successfully");
                    callPlaced = true
                    createLocalStream();
                    initializedAgoraListeners();
                    // if(callPlaced){
                    //     video_container.classList.remove('hide')
                    // }
                },
                (err) => {
                    console.log("Join channel failed", err);
                }
            );
        }

       async function acceptCall(){
        console.log('call accept');
            initializeAgora();
            const tokenRes = await generateToken(agoraChannel);
            joinRoom(tokenRes.data, agoraChannel);
            incomingCall = false;
            callPlaced = true;
            if(callPlaced){
                video_container.classList.remove('hide')
            }
            incoming_call.classList.add('hide')
        }

        function declineCall() {
            // You can send a request to the caller to
            // alert them of rejected call
            incomingCall = false
            incoming_call.classList.add('hide')
            console.log('decline');

                    if(!incomingCall){
                        video_container.classList.add('hide')
                    }
        }


        function generateToken(channelName) {
                                return axios.post("/agora/token", {
                                    channelName,
                                });
                            }

       function initializeAgora() {
            client = AgoraRTC.createClient({ mode: "rtc", codec: "h264" });
            client.init(
                agora_id,
                () => {
                    console.log("AgoraRTC client initialized");
                },
                (err) => {
                    console.log("AgoraRTC client init failed", err);
                }
            );
        }

       function initializedAgoraListeners() {
            //   Register event listeners
            client.on("stream-published", function (evt) {
                console.log("Publish local stream successfully");
                console.log(evt);
            });
            //subscribe remote stream
            client.on("stream-added", ({ stream }) => {
                console.log("New stream added: " + stream.getId());
                client.subscribe(stream, function (err) {
                    console.log("Subscribe stream failed", err);
                });
            });
            client.on("stream-subscribed", (evt) => {
                // Attach remote stream to the remote-video div
                evt.stream.play("remote-video");
                client.publish(evt.stream);
            });
            client.on("stream-removed", ({ stream }) => {
                console.log(String(stream.getId()));
                stream.close();
            });
            client.on("peer-online", (evt) => {
                console.log("peer-online", evt.uid);
            });
            client.on("peer-leave", (evt) => {
                var uid = evt.uid;
                var reason = evt.reason;
                console.log("remote user left ", uid, "reason: ", reason);
            });
            client.on("stream-unpublished", (evt) => {
                console.log(evt);
            });
        }

        function createLocalStream() {
            localStream = AgoraRTC.createStream({
                            audio: true,
                            video: true,
                        });
            // Initialize the local stream
            localStream.init(
                () => {
                    // Play the local stream
                    localStream.play("local-video");
                    // Publish the local stream
                    client.publish(localStream, (data) => {
                        console.log("publish local stream", data);
                    });
                },
                (err) => {
                    console.log(err);
                }
            );
        }

       function handleAudioToggle() {
            if (mutedAudio) {
                localStream.unmuteAudio();
                mutedAudio = false;
                muteAudio.innerText = 'Unmute Audio'
            } else {
                localStream.muteAudio();
                mutedAudio = true;
                muteAudio.innerText = 'Mute Audio'
            }
        }

       function handleVideoToggle() {
            if (mutedVideo) {
                localStream.unmuteVideo();
                mutedVideo = false;
                muteAudio.innerText = 'Unmute Video'
            } else {
                localStream.muteVideo();
                mutedVideo = true;
                muteAudio.innerText = 'Mute Video'
            }
        }

        function endCall(){
            localStream.close();
            client.leave(
                () => {
                    console.log("Leave channel successfully");
                    callPlaced = false;
                    if(!callPlaced){
                        video_container.classList.add('hide')
                    }
                },
                (err) => {
                    console.log("Leave channel failed");
                }
            );
        }


</script>

@endpush
